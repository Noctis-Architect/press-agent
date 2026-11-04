<?php
/**
 * WooCommerce product and order operations via official CRUD APIs.
 *
 * @package PressAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PressAgent_Commerce_Manager {

    public static function is_active() {
        return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' );
    }

    public static function list_products( $args = array() ) {
        $guard = self::guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }

        $limit  = min( 100, max( 1, (int) ( $args['limit'] ?? 20 ) ) );
        $page   = max( 1, (int) ( $args['page'] ?? 1 ) );
        $status = $args['status'] ?? 'publish';
        if ( ! in_array( $status, array( 'publish', 'draft', 'private', 'pending', 'any' ), true ) ) {
            $status = 'publish';
        }

        $query = array(
            'limit'    => $limit,
            'page'     => $page,
            'paginate' => true,
            'status'   => $status,
            'orderby'  => 'date',
            'order'    => 'DESC',
        );
        if ( ! empty( $args['search'] ) ) {
            $query['s'] = sanitize_text_field( $args['search'] );
        }
        if ( ! empty( $args['category'] ) ) {
            $query['category'] = array( sanitize_title( $args['category'] ) );
        }

        $result   = wc_get_products( $query );
        $products = array();
        foreach ( $result->products as $product ) {
            if ( ! $product instanceof WC_Product ) {
                continue;
            }
            $products[] = self::product_summary( $product );
        }

        return array(
            'total'    => (int) $result->total,
            'page'     => $page,
            'limit'    => $limit,
            'products' => $products,
        );
    }

    public static function get_product( $product_id, $include_description = false ) {
        $guard = self::guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }

        $product = wc_get_product( (int) $product_id );
        if ( ! $product ) {
            return new WP_Error( 'product_missing', 'Product not found: ' . $product_id, array( 'status' => 404 ) );
        }

        $data = self::product_summary( $product );
        $data['description']       = $include_description ? $product->get_description() : null;
        $data['short_description'] = $include_description ? $product->get_short_description() : null;
        $categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
        $data['categories']        = is_wp_error( $categories ) ? array() : $categories;
        $data['type']              = $product->get_type();
        $data['permalink']         = get_permalink( $product->get_id() );
        $data['image_url']         = wp_get_attachment_url( $product->get_image_id() ) ?: null;
        $data['attributes']        = $product->get_attributes() ? array_keys( $product->get_attributes() ) : array();

        return $data;
    }

    public static function create_product( $data ) {
        $guard = self::guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        if ( ! class_exists( 'WC_Product_Simple' ) ) {
            return new WP_Error( 'wc_missing', 'WooCommerce product classes are unavailable.', array( 'status' => 500 ) );
        }

        $name = sanitize_text_field( $data['name'] ?? '' );
        if ( '' === $name ) {
            return new WP_Error( 'missing_name', 'Product name is required.', array( 'status' => 400 ) );
        }

        $sku = isset( $data['sku'] ) ? (string) $data['sku'] : '';
        if ( '' !== $sku && wc_get_product_id_by_sku( $sku ) ) {
            return new WP_Error( 'duplicate_sku', 'A product with SKU "' . $sku . '" already exists.', array( 'status' => 409 ) );
        }

        $product = new WC_Product_Simple();
        $product->set_name( $name );
        $product->set_status( self::sanitize_status( $data['status'] ?? 'draft' ) );
        $product->set_regular_price( wc_format_decimal( $data['regular_price'] ?? '' ) );
        if ( isset( $data['sale_price'] ) ) {
            $product->set_sale_price( wc_format_decimal( $data['sale_price'] ) );
        }
        if ( '' !== $sku ) {
            $product->set_sku( $sku );
        }
        if ( isset( $data['description'] ) ) {
            $product->set_description( wp_kses_post( $data['description'] ) );
        }
        if ( isset( $data['short_description'] ) ) {
            $product->set_short_description( wp_kses_post( $data['short_description'] ) );
        }
        if ( isset( $data['manage_stock'] ) ) {
            $product->set_manage_stock( rest_sanitize_boolean( $data['manage_stock'] ) );
        }
        if ( isset( $data['stock_quantity'] ) && '' !== $data['stock_quantity'] ) {
            $product->set_stock_quantity( (float) $data['stock_quantity'] );
        }
        if ( ! empty( $data['stock_status'] ) ) {
            $product->set_stock_status( self::sanitize_stock_status( $data['stock_status'] ) );
        }
        if ( ! empty( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) {
            $product->set_category_ids( array_map( 'absint', $data['category_ids'] ) );
        }
        if ( ! empty( $data['image_id'] ) ) {
            $product->set_image_id( absint( $data['image_id'] ) );
        }

        $product_id = $product->save();

        return array(
            'success'    => true,
            'product_id' => $product_id,
            'name'       => $product->get_name(),
            'status'     => $product->get_status(),
            'price'      => $product->get_price(),
            'sku'        => $product->get_sku(),
            'permalink'  => get_permalink( $product_id ),
            'message'    => 'Product created.',
        );
    }

    public static function update_product( $product_id, $data ) {
        $guard = self::guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }

        $product = wc_get_product( (int) $product_id );
        if ( ! $product ) {
            return new WP_Error( 'product_missing', 'Product not found: ' . $product_id, array( 'status' => 404 ) );
        }

        $is_variable = 'variable' === $product->get_type();
        $price_keys  = array( 'regular_price', 'sale_price', 'stock_quantity', 'stock_status', 'manage_stock' );
        if ( $is_variable && ! empty( array_intersect( $price_keys, array_keys( $data ) ) ) ) {
            return new WP_Error(
                'variable_product',
                'Variable product pricing/stock belongs to variations. Update variations via WooCommerce, or edit the parent name/status only.',
                array( 'status' => 409 )
            );
        }

        $changed = array();

        if ( isset( $data['name'] ) && '' !== trim( (string) $data['name'] ) ) {
            $product->set_name( sanitize_text_field( $data['name'] ) );
            $changed[] = 'name';
        }
        if ( isset( $data['status'] ) ) {
            $product->set_status( self::sanitize_status( $data['status'] ) );
            $changed[] = 'status';
        }
        if ( isset( $data['regular_price'] ) ) {
            $product->set_regular_price( wc_format_decimal( $data['regular_price'] ) );
            $changed[] = 'regular_price';
        }
        if ( array_key_exists( 'sale_price', $data ) ) {
            $product->set_sale_price( wc_format_decimal( $data['sale_price'] ) );
            $changed[] = 'sale_price';
        }
        if ( isset( $data['sku'] ) ) {
            $sku      = (string) $data['sku'];
            $existing = '' !== $sku ? wc_get_product_id_by_sku( $sku ) : 0;
            if ( $existing && (int) $existing !== $product->get_id() ) {
                return new WP_Error( 'duplicate_sku', 'Another product already uses SKU "' . $sku . '".', array( 'status' => 409 ) );
            }
            $product->set_sku( $sku );
            $changed[] = 'sku';
        }
        if ( isset( $data['description'] ) ) {
            $product->set_description( wp_kses_post( $data['description'] ) );
            $changed[] = 'description';
        }
        if ( isset( $data['short_description'] ) ) {
            $product->set_short_description( wp_kses_post( $data['short_description'] ) );
            $changed[] = 'short_description';
        }
        if ( isset( $data['manage_stock'] ) ) {
            $product->set_manage_stock( rest_sanitize_boolean( $data['manage_stock'] ) );
            $changed[] = 'manage_stock';
        }
        if ( isset( $data['stock_quantity'] ) && '' !== $data['stock_quantity'] ) {
            $product->set_stock_quantity( (float) $data['stock_quantity'] );
            $changed[] = 'stock_quantity';
        }
        if ( ! empty( $data['stock_status'] ) ) {
            $product->set_stock_status( self::sanitize_stock_status( $data['stock_status'] ) );
            $changed[] = 'stock_status';
        }

        $product->save();

        return array(
            'success'    => true,
            'product_id' => $product->get_id(),
            'changed'    => $changed,
            'price'      => $product->get_price(),
            'stock'      => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'message'    => 'Product updated.',
        );
    }

    public static function list_orders( $args = array() ) {
        $guard = self::guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }

        $limit = min( 50, max( 1, (int) ( $args['limit'] ?? 20 ) ) );
        $page  = max( 1, (int) ( $args['page'] ?? 1 ) );
        $query = array(
            'limit'   => $limit + 1,
            'page'    => $page,
            'orderby' => 'date',
            'order'   => 'DESC',
            'type'    => 'shop_order',
        );
        if ( ! empty( $args['status'] ) ) {
            $query['status'] = self::normalize_order_status( $args['status'] );
        }

        $orders   = wc_get_orders( $query );
        $has_more = count( $orders ) > $limit;
        $orders   = array_slice( $orders, 0, $limit );
        $items    = array();

        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Order ) {
                continue;
            }
            $items[] = array(
                'id'          => $order->get_id(),
                'number'      => $order->get_order_number(),
                'status'      => $order->get_status(),
                'total'       => $order->get_total(),
                'currency'    => $order->get_currency(),
                'date'        => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
                'customer'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                'email'       => $order->get_billing_email(),
                'item_count'  => $order->get_item_count(),
            );
        }

        return array(
            'page'     => $page,
            'limit'    => $limit,
            'has_more' => $has_more,
            'orders'   => $items,
        );
    }

    public static function get_order( $order_id ) {
        $guard = self::guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }

        $order = wc_get_order( (int) $order_id );
        if ( ! $order ) {
            return new WP_Error( 'order_missing', 'Order not found: ' . $order_id, array( 'status' => 404 ) );
        }

        $lines = array();
        foreach ( $order->get_items() as $item ) {
            $lines[] = array(
                'name'     => $item->get_name(),
                'qty'      => $item->get_quantity(),
                'total'    => $item->get_total(),
                'product_id' => $item->get_product_id(),
            );
        }

        return array(
            'id'             => $order->get_id(),
            'number'         => $order->get_order_number(),
            'status'         => $order->get_status(),
            'currency'       => $order->get_currency(),
            'date'           => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
            'customer'       => array(
                'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ),
            'billing_address' => array_filter( array(
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city'      => $order->get_billing_city(),
                'state'     => $order->get_billing_state(),
                'postcode'  => $order->get_billing_postcode(),
                'country'   => $order->get_billing_country(),
            ) ),
            'payment_method' => $order->get_payment_method_title(),
            'totals'         => array(
                'subtotal' => $order->get_subtotal(),
                'shipping' => $order->get_shipping_total(),
                'tax'      => $order->get_total_tax(),
                'discount' => $order->get_discount_total(),
                'total'    => $order->get_total(),
            ),
            'items'          => $lines,
            'customer_note'  => $order->get_customer_note(),
        );
    }

    public static function update_order_status( $order_id, $status, $note = '', $customer_note = false ) {
        $guard = self::guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }

        $order = wc_get_order( (int) $order_id );
        if ( ! $order ) {
            return new WP_Error( 'order_missing', 'Order not found: ' . $order_id, array( 'status' => 404 ) );
        }

        $status = self::normalize_order_status( $status );
        if ( ! in_array( $status, array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ), true ) ) {
            return new WP_Error( 'invalid_status', 'Invalid order status: ' . $status, array( 'status' => 400 ) );
        }

        $old = $order->get_status();
        $order->update_status( $status, sanitize_textarea_field( (string) $note ), true );
        if ( $customer_note && '' !== trim( (string) $note ) ) {
            $order->add_order_note( sanitize_textarea_field( $note ), 1, true );
        }

        return array(
            'success'   => true,
            'order_id'  => $order->get_id(),
            'old_status'=> $old,
            'new_status'=> $order->get_status(),
            'message'   => 'Order status updated.',
        );
    }

    public static function add_order_note( $order_id, $note, $customer_note = false ) {
        $guard = self::guard();
        if ( is_wp_error( $guard ) ) {
            return $guard;
        }
        $order = wc_get_order( (int) $order_id );
        if ( ! $order ) {
            return new WP_Error( 'order_missing', 'Order not found: ' . $order_id, array( 'status' => 404 ) );
        }
        $note = sanitize_textarea_field( (string) $note );
        if ( '' === $note ) {
            return new WP_Error( 'missing_note', 'Note text is required.', array( 'status' => 400 ) );
        }
        $note_id = $order->add_order_note( $note, $customer_note ? 1 : 0, true );
        return array(
            'success'  => true,
            'order_id' => $order->get_id(),
            'note_id'  => $note_id,
            'customer_note' => (bool) $customer_note,
            'message'  => 'Order note added.',
        );
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------- */

    private static function guard() {
        if ( ! self::is_active() ) {
            return new WP_Error( 'woocommerce_missing', 'WooCommerce is not active.', array( 'status' => 409 ) );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error( 'forbidden', 'You do not have permission to manage WooCommerce data.', array( 'status' => 403 ) );
        }
        return true;
    }

    private static function product_summary( $product ) {
        return array(
            'id'             => $product->get_id(),
            'name'           => $product->get_name(),
            'sku'            => $product->get_sku(),
            'status'         => $product->get_status(),
            'price'          => $product->get_price(),
            'regular_price'  => $product->get_regular_price(),
            'sale_price'     => $product->get_sale_price(),
            'stock_status'   => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'manage_stock'   => $product->get_manage_stock(),
            'type'           => $product->get_type(),
        );
    }

    private static function sanitize_status( $status ) {
        $allowed = array( 'publish', 'draft', 'private', 'pending' );
        return in_array( $status, $allowed, true ) ? $status : 'draft';
    }

    private static function sanitize_stock_status( $status ) {
        $allowed = array( 'instock', 'outofstock', 'onbackorder' );
        return in_array( $status, $allowed, true ) ? $status : 'instock';
    }

    private static function normalize_order_status( $status ) {
        $status = sanitize_key( (string) $status );
        return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
    }
}

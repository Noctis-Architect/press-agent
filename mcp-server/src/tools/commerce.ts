import { WPClient } from "../wp-client.js";
import { z } from "zod";

const ACTIONS = [
  "list_products",
  "get_product",
  "create_product",
  "update_product",
  "list_orders",
  "get_order",
  "update_order_status",
  "add_order_note"
] as const;

export function registerCommerceTools(
  tools: any[],
  handlers: Map<string, (args: any) => Promise<any>>,
  client: WPClient
) {
  tools.push({
    name: "pressagent_commerce",
    description: `WooCommerce products and orders via official CRUD APIs (HPOS-safe).

Actions: list_products {limit,page,search,status} | get_product {product_id,include_description} | create_product / update_product {name,sku,regular_price,sale_price,stock_quantity,stock_status,status,description} | list_orders {limit,page,status} | get_order {order_id} | update_order_status {order_id,status,note,customer_note} | add_order_note {order_id,note,customer_note}.

Variable-product pricing lives on variations; parent price/stock writes are rejected.`,
    inputSchema: {
      type: "object",
      properties: {
        action: { type: "string", enum: ACTIONS },
        product_id: { type: "number" },
        order_id: { type: "number" },
        name: { type: "string" },
        sku: { type: "string" },
        regular_price: { type: "string" },
        sale_price: { type: "string" },
        stock_quantity: { type: "number" },
        stock_status: { type: "string", enum: ["instock", "outofstock", "onbackorder"] },
        manage_stock: { type: "boolean" },
        status: { type: "string", description: "Product or order status" },
        description: { type: "string" },
        short_description: { type: "string" },
        include_description: { type: "boolean" },
        search: { type: "string" },
        limit: { type: "number" },
        page: { type: "number" },
        note: { type: "string" },
        customer_note: { type: "boolean" }
      },
      required: ["action"]
    }
  });

  handlers.set("pressagent_commerce", async (args) => {
    const schema = z.object({
      action: z.enum(ACTIONS),
      product_id: z.number().int().positive().optional(),
      order_id: z.number().int().positive().optional(),
      name: z.string().optional(),
      sku: z.string().optional(),
      regular_price: z.string().optional(),
      sale_price: z.string().optional(),
      stock_quantity: z.number().optional(),
      stock_status: z.enum(["instock", "outofstock", "onbackorder"]).optional(),
      manage_stock: z.boolean().optional(),
      status: z.string().optional(),
      description: z.string().optional(),
      short_description: z.string().optional(),
      include_description: z.boolean().optional().default(false),
      search: z.string().optional(),
      limit: z.number().int().positive().max(100).optional(),
      page: z.number().int().positive().optional(),
      note: z.string().optional(),
      customer_note: z.boolean().optional().default(false)
    });
    const parsed = schema.parse(args);

    switch (parsed.action) {
      case "list_products":
        return client.listProducts({
          limit: parsed.limit,
          page: parsed.page,
          search: parsed.search,
          status: parsed.status
        });
      case "get_product":
        if (!parsed.product_id) throw new Error('"product_id" is required for get_product.');
        return client.getProduct(parsed.product_id, parsed.include_description);
      case "create_product": {
        if (!parsed.name) throw new Error('"name" is required for create_product.');
        const data: Record<string, any> = {
          name: parsed.name,
          sku: parsed.sku,
          regular_price: parsed.regular_price,
          sale_price: parsed.sale_price,
          stock_quantity: parsed.stock_quantity,
          stock_status: parsed.stock_status,
          manage_stock: parsed.manage_stock,
          status: parsed.status,
          description: parsed.description,
          short_description: parsed.short_description
        };
        return client.createProduct(compact(data));
      }
      case "update_product": {
        if (!parsed.product_id) throw new Error('"product_id" is required for update_product.');
        const data: Record<string, any> = {
          name: parsed.name,
          sku: parsed.sku,
          regular_price: parsed.regular_price,
          sale_price: parsed.sale_price,
          stock_quantity: parsed.stock_quantity,
          stock_status: parsed.stock_status,
          manage_stock: parsed.manage_stock,
          status: parsed.status,
          description: parsed.description,
          short_description: parsed.short_description
        };
        return client.updateProduct(parsed.product_id, compact(data));
      }
      case "list_orders":
        return client.listOrders({ limit: parsed.limit, page: parsed.page, status: parsed.status });
      case "get_order":
        if (!parsed.order_id) throw new Error('"order_id" is required for get_order.');
        return client.getOrder(parsed.order_id);
      case "update_order_status":
        if (!parsed.order_id || !parsed.status) throw new Error('"order_id" and "status" are required.');
        return client.updateOrderStatus(parsed.order_id, parsed.status, parsed.note || "", parsed.customer_note);
      case "add_order_note":
        if (!parsed.order_id || !parsed.note) throw new Error('"order_id" and "note" are required.');
        return client.addOrderNote(parsed.order_id, parsed.note, parsed.customer_note);
    }
  });
}

function compact(data: Record<string, any>): Record<string, any> {
  return Object.fromEntries(Object.entries(data).filter(([, value]) => value !== undefined));
}

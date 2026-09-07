<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

if ( isset( $_POST['pressagent_generate_token'] ) && check_admin_referer( 'pressagent_generate_token_nonce' ) ) {
    $label = sanitize_text_field( $_POST['token_label'] );
    $scopes = isset( $_POST['token_scopes'] ) ? array_map( 'sanitize_text_field', $_POST['token_scopes'] ) : array();
    $new_token = PressAgent_Auth::generate_token( $label, $scopes );
}

if ( isset( $_POST['pressagent_revoke_token'] ) && check_admin_referer( 'pressagent_revoke_token_nonce' ) ) {
    $token_id = sanitize_text_field( $_POST['token_id'] );
    PressAgent_Auth::revoke_token( $token_id );
}

$tokens = get_option( 'pressagent_tokens', array() );
$available_scopes = array( 'pages:read', 'pages:write', 'elementor:read', 'elementor:write', 'settings:read', 'settings:write', 'debug:read', 'debug:write', 'security:read', 'code:execute' );
?>

<div class="wrap">
    <h1>PressAgent Settings</h1>

    <?php if ( isset( $new_token ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>New Token Generated:</strong> <code><?php echo esc_html( $new_token ); ?></code></p>
            <p><em>Please copy this token now. It will not be shown again.</em></p>
        </div>
    <?php endif; ?>

    <h2>Token Management</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Label</th>
                <th>Scopes</th>
                <th>Created At</th>
                <th>Last Used</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $tokens ) ) : ?>
                <tr><td colspan="5">No tokens found.</td></tr>
            <?php else : ?>
                <?php foreach ( $tokens as $id => $token ) : ?>
                    <tr>
                        <td><?php echo esc_html( $token['label'] ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $token['scopes'] ) ); ?></td>
                        <td><?php echo esc_html( $token['created_at'] ); ?></td>
                        <td><?php echo esc_html( $token['last_used'] ?: 'Never' ); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field( 'pressagent_revoke_token_nonce' ); ?>
                                <input type="hidden" name="token_id" value="<?php echo esc_attr( $id ); ?>">
                                <input type="hidden" name="pressagent_revoke_token" value="1">
                                <button type="submit" class="button button-link-delete" onclick="return confirm('Are you sure?');">Revoke</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h3>Generate New Token</h3>
    <form method="post">
        <?php wp_nonce_field( 'pressagent_generate_token_nonce' ); ?>
        <input type="hidden" name="pressagent_generate_token" value="1">
        <table class="form-table">
            <tr>
                <th scope="row"><label for="token_label">Token Label</label></th>
                <td><input type="text" name="token_label" id="token_label" class="regular-text" required></td>
            </tr>
            <tr>
                <th scope="row">Scopes</th>
                <td>
                    <?php foreach ( $available_scopes as $scope ) : ?>
                        <label>
                            <input type="checkbox" name="token_scopes[]" value="<?php echo esc_attr( $scope ); ?>">
                            <?php echo esc_html( $scope ); ?>
                        </label><br>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Generate Token' ); ?>
    </form>

    <h2>God Mode</h2>
    <div class="notice notice-warning inline">
        <p><strong>Warning:</strong> God Mode (Code Executor) allows execution of arbitrary PHP code. It is currently hardcoded to <strong>disabled</strong> in the plugin for security reasons.</p>
    </div>
</div>

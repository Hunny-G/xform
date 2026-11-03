<?php
class Test_XForm_Forms extends WP_UnitTestCase {
    public function test_missing_form_returns_null(): void {
        $this->assertNull( XForm_Forms::get( 'does-not-exist' ) );
    }
    public function test_form_nonce_is_generated_by_wordpress(): void {
        $nonce = wp_create_nonce( 'xform_submit' );
        $this->assertTrue( wp_verify_nonce( $nonce, 'xform_submit' ) > 0 );
    }
    public function test_webhook_requires_https_and_host(): void {
        $this->assertTrue( XForm_Forms::is_valid_webhook_url( 'https://hooks.example.com/receive' ) );
        $this->assertFalse( XForm_Forms::is_valid_webhook_url( 'http://hooks.example.com/receive' ) );
        $this->assertFalse( XForm_Forms::is_valid_webhook_url( 'https:///missing-host' ) );
    }
    public function test_webhook_rejects_embedded_credentials_and_fragments(): void {
        $this->assertFalse( XForm_Forms::is_valid_webhook_url( 'https://user:pass@hooks.example.com/receive' ) );
        $this->assertFalse( XForm_Forms::is_valid_webhook_url( 'https://hooks.example.com/receive#fragment' ) );
    }
}

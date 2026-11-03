<?php
class Test_XForm_Forms extends WP_UnitTestCase {
    public function test_missing_form_returns_null(): void {
        $this->assertNull( XForm_Forms::get( 'does-not-exist' ) );
    }
    public function test_form_nonce_is_generated_by_wordpress(): void {
        $nonce = wp_create_nonce( 'xform_submit' );
        $this->assertTrue( wp_verify_nonce( $nonce, 'xform_submit' ) > 0 );
    }
}

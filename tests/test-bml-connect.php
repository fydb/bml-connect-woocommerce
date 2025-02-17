<?php
/**
 * BML Connect Unit Tests
 */
class BML_Connect_Tests extends WP_UnitTestCase {
    private $gateway;
    
    public function setUp() {
        parent::setUp();
        $this->gateway = new BML_Connect_Gateway();
    }
    
    public function test_gateway_initialization() {
        $this->assertEquals('bml_connect', $this->gateway->id);
        $this->assertFalse($this->gateway->has_fields);
        $this->assertTrue(isset($this->gateway->form_fields['enabled']));
    }
    
    public function test_signature_generation() {
        $api = new BML_Connect_API($this->gateway);
        $data = [
            'amount' => '100.00',
            'currency' => 'MVR',
            'orderReference' => '12345'
        ];
        
        $signature = $this->invoke_private_method($api, 'generate_signature', [$data]);
        $this->assertNotEmpty($signature);
        $this->assertEquals(32, strlen($signature));
    }
    
    public function test_payment_validation() {
        $data = [
            'amount' => '100.00',
            'currency' => 'MVR',
            'orderReference' => '12345'
        ];
        
        $sanitized = BML_Connect_Security::sanitize_payment_data($data);
        $this->assertEquals($data['amount'], $sanitized['amount']);
        $this->assertEquals($data['currency'], $sanitized['currency']);
    }
    
    public function test_invalid_payment_data() {
        $this->expectException(Exception::class);
        
        $data = [
            'amount' => '-100.00',
            'currency' => 'MVR',
            'orderReference' => '12345'
        ];
        
        BML_Connect_Security::sanitize_payment_data($data);
    }
    
    public function test_transaction_logging() {
        BML_Connect_Logger::log('Test message');
        $this->assertTrue(true); // Just ensure no errors
    }
    
    private function invoke_private_method($object, $method_name, $parameters = []) {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method_name);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    private WhatsAppService $whatsappService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->whatsappService = new WhatsAppService();
    }

    public function test_clean_phone_number_with_full_format(): void
    {
        // Número ya con formato completo 549XXXXXXXXX
        $phone = '5491123456789';
        $result = $this->callPrivateMethod('cleanPhoneNumber', [$phone]);
        $this->assertEquals('5491123456789', $result);
    }

    public function test_clean_phone_number_with_country_code_only(): void
    {
        // Número con código de país 54XXXXXXXXX
        $phone = '541123456789';
        $result = $this->callPrivateMethod('cleanPhoneNumber', [$phone]);
        $this->assertEquals('5491123456789', $result);
    }

    public function test_clean_phone_number_without_country_code(): void
    {
        // Número sin código de país
        $phone = '1123456789';
        $result = $this->callPrivateMethod('cleanPhoneNumber', [$phone]);
        $this->assertEquals('5491123456789', $result);
    }

    public function test_clean_phone_number_with_spaces_and_dashes(): void
    {
        // Número con espacios y guiones
        $phone = '+54 11 1234-5678';
        $result = $this->callPrivateMethod('cleanPhoneNumber', [$phone]);
        $this->assertEquals('5491112345678', $result);
    }

    public function test_clean_phone_number_with_parentheses(): void
    {
        // Número con paréntesis
        $phone = '(54) 11 1234-5678';
        $result = $this->callPrivateMethod('cleanPhoneNumber', [$phone]);
        $this->assertEquals('5491112345678', $result);
    }

    public function test_clean_phone_number_already_has_nine(): void
    {
        // Número que ya tiene el 9 después del 54
        $phone = '5491123456789';
        $result = $this->callPrivateMethod('cleanPhoneNumber', [$phone]);
        $this->assertEquals('5491123456789', $result);
    }

    public function test_clean_phone_number_mobile_without_area_code(): void
    {
        // Número móvil sin código de área
        $phone = '9123456789';
        $result = $this->callPrivateMethod('cleanPhoneNumber', [$phone]);
        $this->assertEquals('5499123456789', $result);
    }

    public function test_clean_phone_number_with_plus_sign(): void
    {
        // Número con signo más
        $phone = '+541123456789';
        $result = $this->callPrivateMethod('cleanPhoneNumber', [$phone]);
        $this->assertEquals('5491123456789', $result);
    }

    public function test_clean_phone_number_landline_without_nine(): void
    {
        // Número fijo sin el 9 (debería agregarlo)
        $phone = '54112345678';
        $result = $this->callPrivateMethod('cleanPhoneNumber', [$phone]);
        $this->assertEquals('549112345678', $result);
    }

    /**
     * Helper method to call private methods for testing
     */
    private function callPrivateMethod(string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($this->whatsappService);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($this->whatsappService, $parameters);
    }
}

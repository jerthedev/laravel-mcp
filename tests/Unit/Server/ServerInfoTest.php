<?php

namespace JTD\LaravelMCP\Tests\Unit\Server;

use Illuminate\Support\Facades\Config;
use JTD\LaravelMCP\Server\ServerInfo;
use JTD\LaravelMCP\Tests\TestCase;

class ServerInfoTest extends TestCase
{
    private ServerInfo $serverInfo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverInfo = new ServerInfo;
    }

    public function test_can_create_server_info_instance(): void
    {
        $this->assertInstanceOf(ServerInfo::class, $this->serverInfo);
    }

    public function test_has_default_server_information(): void
    {
        $info = $this->serverInfo->getServerInfo();

        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('version', $info);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('vendor', $info);
        $this->assertArrayHasKey('protocolVersion', $info);
        $this->assertArrayHasKey('implementation', $info);
        $this->assertArrayHasKey('runtime', $info);
        $this->assertArrayHasKey('startTime', $info);
        $this->assertArrayHasKey('uptime', $info);
    }

    public function test_can_get_basic_info(): void
    {
        $basicInfo = $this->serverInfo->getBasicInfo();

        $this->assertArrayHasKey('name', $basicInfo);
        $this->assertArrayHasKey('version', $basicInfo);
        $this->assertCount(2, $basicInfo);
    }

    public function test_can_get_protocol_version(): void
    {
        $protocolVersion = $this->serverInfo->getProtocolVersion();

        $this->assertEquals('2024-11-05', $protocolVersion);
    }

    public function test_can_get_uptime(): void
    {
        $uptime = $this->serverInfo->getUptime();

        $this->assertIsInt($uptime);
        $this->assertGreaterThanOrEqual(0, $uptime);
    }

    public function test_can_get_start_time(): void
    {
        $startTime = $this->serverInfo->getStartTime();

        $this->assertIsInt($startTime);
        $this->assertLessThanOrEqual(time(), $startTime);
    }

    public function test_can_get_name(): void
    {
        $name = $this->serverInfo->getName();

        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    public function test_can_get_version(): void
    {
        $version = $this->serverInfo->getVersion();

        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }

    public function test_can_get_description(): void
    {
        $description = $this->serverInfo->getDescription();

        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function test_can_get_vendor(): void
    {
        $vendor = $this->serverInfo->getVendor();

        $this->assertIsString($vendor);
        $this->assertNotEmpty($vendor);
    }

    public function test_can_set_name(): void
    {
        $newName = 'Test Server';
        $this->serverInfo->setName($newName);

        $this->assertEquals($newName, $this->serverInfo->getName());
    }

    public function test_can_set_version(): void
    {
        $newVersion = '2.0.0';
        $this->serverInfo->setVersion($newVersion);

        $this->assertEquals($newVersion, $this->serverInfo->getVersion());
    }

    public function test_can_set_description(): void
    {
        $newDescription = 'Test Description';
        $this->serverInfo->setDescription($newDescription);

        $this->assertEquals($newDescription, $this->serverInfo->getDescription());
    }

    public function test_can_set_vendor(): void
    {
        $newVendor = 'Test Vendor';
        $this->serverInfo->setVendor($newVendor);

        $this->assertEquals($newVendor, $this->serverInfo->getVendor());
    }

    public function test_can_update_server_info(): void
    {
        $updates = [
            'name' => 'Updated Server',
            'version' => '3.0.0',
        ];

        $this->serverInfo->updateServerInfo($updates);

        $this->assertEquals('Updated Server', $this->serverInfo->getName());
        $this->assertEquals('3.0.0', $this->serverInfo->getVersion());
    }

    public function test_can_update_runtime_info(): void
    {
        $updates = [
            'test_property' => 'test_value',
        ];

        $this->serverInfo->updateRuntimeInfo($updates);
        $runtimeInfo = $this->serverInfo->getRuntimeInfo();

        $this->assertArrayHasKey('test_property', $runtimeInfo);
        $this->assertEquals('test_value', $runtimeInfo['test_property']);
    }

    public function test_can_get_status(): void
    {
        $status = $this->serverInfo->getStatus();

        $this->assertArrayHasKey('server', $status);
        $this->assertArrayHasKey('uptime', $status);
        $this->assertArrayHasKey('start_time', $status);
        $this->assertArrayHasKey('memory_usage', $status);
        $this->assertArrayHasKey('peak_memory', $status);
        $this->assertArrayHasKey('environment', $status);
        $this->assertArrayHasKey('timezone', $status);
    }

    public function test_can_get_detailed_info(): void
    {
        $detailedInfo = $this->serverInfo->getDetailedInfo();

        $this->assertArrayHasKey('server', $detailedInfo);
        $this->assertArrayHasKey('runtime', $detailedInfo);
        $this->assertArrayHasKey('system', $detailedInfo);
        $this->assertArrayHasKey('performance', $detailedInfo);
    }

    public function test_can_get_formatted_uptime(): void
    {
        $formattedUptime = $this->serverInfo->getUptimeFormatted();

        $this->assertIsString($formattedUptime);
        $this->assertStringContainsString('s', $formattedUptime);
    }

    public function test_can_reset_start_time(): void
    {
        $originalStartTime = $this->serverInfo->getStartTime();
        sleep(1);

        $this->serverInfo->resetStartTime();
        $newStartTime = $this->serverInfo->getStartTime();

        $this->assertGreaterThan($originalStartTime, $newStartTime);
    }

    public function test_can_convert_to_json(): void
    {
        $json = $this->serverInfo->toJson();

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('name', $decoded);
        $this->assertArrayHasKey('version', $decoded);
    }

    public function test_can_convert_to_array(): void
    {
        $array = $this->serverInfo->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('version', $array);
    }

    public function test_respects_configuration(): void
    {
        Config::set('laravel-mcp.server.name', 'Config Test Server');
        Config::set('laravel-mcp.server.version', '5.0.0');
        Config::set('laravel-mcp.server.description', 'Config Test Description');
        Config::set('laravel-mcp.server.vendor', 'Config Test Vendor');

        $serverInfo = new ServerInfo;

        $this->assertEquals('Config Test Server', $serverInfo->getName());
        $this->assertEquals('5.0.0', $serverInfo->getVersion());
        $this->assertEquals('Config Test Description', $serverInfo->getDescription());
        $this->assertEquals('Config Test Vendor', $serverInfo->getVendor());
    }

    public function test_respects_environment_variables(): void
    {
        Config::set('laravel-mcp.server.name', env('MCP_SERVER_NAME', 'Env Test Server'));
        Config::set('laravel-mcp.server.description', env('MCP_SERVER_DESCRIPTION', 'Env Test Description'));

        $serverInfo = new ServerInfo;

        $name = $serverInfo->getName();
        $description = $serverInfo->getDescription();

        $this->assertIsString($name);
        $this->assertIsString($description);
    }

    public function test_runtime_info_contains_required_fields(): void
    {
        $runtimeInfo = $this->serverInfo->getRuntimeInfo();

        $this->assertArrayHasKey('php_version', $runtimeInfo);
        $this->assertArrayHasKey('laravel_version', $runtimeInfo);
        $this->assertArrayHasKey('environment', $runtimeInfo);
        $this->assertArrayHasKey('timezone', $runtimeInfo);

        $this->assertEquals(PHP_VERSION, $runtimeInfo['php_version']);
    }

    public function test_implementation_info_is_correct(): void
    {
        $serverInfo = $this->serverInfo->getServerInfo();
        $implementation = $serverInfo['implementation'];

        $this->assertArrayHasKey('name', $implementation);
        $this->assertArrayHasKey('version', $implementation);
        $this->assertArrayHasKey('repository', $implementation);

        $this->assertEquals('Laravel MCP', $implementation['name']);
        $this->assertStringContainsString('github.com', $implementation['repository']);
    }
}

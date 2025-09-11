<?php

namespace JTD\LaravelMCP\Tests\Unit\Registry;

use JTD\LaravelMCP\Registry\RoutingPatterns;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * Test suite for RoutingPatterns functionality.
 *
 * Tests the routing patterns and conventions for MCP components,
 * including pattern generation, route naming, middleware assignment,
 * and route caching compatibility.
 */
class RoutingPatternsTest extends TestCase
{
    private RoutingPatterns $patterns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patterns = new RoutingPatterns;
    }

    /**
     * Test getting route pattern for valid component types.
     */
    public function test_get_pattern_for_valid_component_types(): void
    {
        $toolPattern = $this->patterns->getPattern('tools');
        $this->assertIsArray($toolPattern);
        $this->assertEquals('tools', $toolPattern['prefix']);
        $this->assertEquals('tools/{tool}', $toolPattern['pattern']);
        $this->assertEquals(['POST'], $toolPattern['methods']);
        $this->assertEquals('mcp.tools.{name}', $toolPattern['name_pattern']);
        $this->assertEquals('executeTool', $toolPattern['controller_action']);

        $resourcePattern = $this->patterns->getPattern('resources');
        $this->assertIsArray($resourcePattern);
        $this->assertEquals('resources', $resourcePattern['prefix']);
        $this->assertEquals('resources/{resource}', $resourcePattern['pattern']);
        $this->assertEquals(['GET', 'POST'], $resourcePattern['methods']);
        $this->assertEquals('mcp.resources.{name}', $resourcePattern['name_pattern']);
        $this->assertEquals('accessResource', $resourcePattern['controller_action']);

        $promptPattern = $this->patterns->getPattern('prompts');
        $this->assertIsArray($promptPattern);
        $this->assertEquals('prompts', $promptPattern['prefix']);
        $this->assertEquals('prompts/{prompt}', $promptPattern['pattern']);
        $this->assertEquals(['GET', 'POST'], $promptPattern['methods']);
        $this->assertEquals('mcp.prompts.{name}', $promptPattern['name_pattern']);
        $this->assertEquals('renderPrompt', $promptPattern['controller_action']);
    }

    /**
     * Test getting pattern for invalid component type.
     */
    public function test_get_pattern_for_invalid_component_type(): void
    {
        $pattern = $this->patterns->getPattern('invalid');
        $this->assertNull($pattern);
    }

    /**
     * Test getting all patterns.
     */
    public function test_get_all_patterns(): void
    {
        $allPatterns = $this->patterns->getAllPatterns();

        $this->assertIsArray($allPatterns);
        $this->assertArrayHasKey('tools', $allPatterns);
        $this->assertArrayHasKey('resources', $allPatterns);
        $this->assertArrayHasKey('prompts', $allPatterns);

        $this->assertCount(3, $allPatterns);
    }

    /**
     * Test route name generation for tools.
     */
    public function test_generate_route_name_for_tools(): void
    {
        $routeName = $this->patterns->generateRouteName('tools', 'Calculator');
        $this->assertEquals('mcp.tools.calculator', $routeName);

        $routeName = $this->patterns->generateRouteName('tools', 'WeatherService');
        $this->assertEquals('mcp.tools.weather_service', $routeName);

        $routeName = $this->patterns->generateRouteName('tools', 'User.Profile.Manager');
        $this->assertEquals('mcp.tools.user_profile_manager', $routeName);
    }

    /**
     * Test route name generation for resources.
     */
    public function test_generate_route_name_for_resources(): void
    {
        $routeName = $this->patterns->generateRouteName('resources', 'UserData');
        $this->assertEquals('mcp.resources.user_data', $routeName);

        $routeName = $this->patterns->generateRouteName('resources', 'file.system.explorer');
        $this->assertEquals('mcp.resources.file_system_explorer', $routeName);
    }

    /**
     * Test route name generation for prompts.
     */
    public function test_generate_route_name_for_prompts(): void
    {
        $routeName = $this->patterns->generateRouteName('prompts', 'EmailTemplate');
        $this->assertEquals('mcp.prompts.email_template', $routeName);

        $routeName = $this->patterns->generateRouteName('prompts', 'CodeGenerator');
        $this->assertEquals('mcp.prompts.code_generator', $routeName);
    }

    /**
     * Test route name generation with action.
     */
    public function test_generate_route_name_with_action(): void
    {
        $routeName = $this->patterns->generateRouteName('tools', 'Calculator', 'execute');
        $this->assertEquals('mcp.tools.calculator.execute', $routeName);

        $routeName = $this->patterns->generateRouteName('resources', 'UserData', 'show');
        $this->assertEquals('mcp.resources.user_data.show', $routeName);

        $routeName = $this->patterns->generateRouteName('prompts', 'EmailTemplate', 'render');
        $this->assertEquals('mcp.prompts.email_template.render', $routeName);
    }

    /**
     * Test route URI generation for tools.
     */
    public function test_generate_route_uri_for_tools(): void
    {
        $uri = $this->patterns->generateRouteUri('tools', 'Calculator');
        $this->assertEquals('tools/calculator', $uri);

        $uri = $this->patterns->generateRouteUri('tools', 'WeatherService');
        $this->assertEquals('tools/weather_service', $uri);

        $uri = $this->patterns->generateRouteUri('tools', 'User.Profile.Manager');
        $this->assertEquals('tools/user_profile_manager', $uri);
    }

    /**
     * Test route URI generation for resources.
     */
    public function test_generate_route_uri_for_resources(): void
    {
        $uri = $this->patterns->generateRouteUri('resources', 'UserData');
        $this->assertEquals('resources/user_data', $uri);

        $uri = $this->patterns->generateRouteUri('resources', 'FileSystemExplorer');
        $this->assertEquals('resources/file_system_explorer', $uri);
    }

    /**
     * Test route URI generation for prompts.
     */
    public function test_generate_route_uri_for_prompts(): void
    {
        $uri = $this->patterns->generateRouteUri('prompts', 'EmailTemplate');
        $this->assertEquals('prompts/email_template', $uri);

        $uri = $this->patterns->generateRouteUri('prompts', 'CodeGenerator');
        $this->assertEquals('prompts/code_generator', $uri);
    }

    /**
     * Test route URI generation with parameters.
     */
    public function test_generate_route_uri_with_parameters(): void
    {
        $uri = $this->patterns->generateRouteUri('tools', 'Calculator', ['version' => 'v1']);
        $this->assertEquals('tools/calculator', $uri); // No placeholder for version in default pattern

        // Test with custom pattern that has parameters
        $this->patterns->setPattern('custom', [
            'pattern' => 'custom/{custom}/{version}',
            'name_pattern' => 'mcp.custom.{name}',
        ]);

        $uri = $this->patterns->generateRouteUri('custom', 'TestComponent', ['version' => 'v2']);
        $this->assertEquals('custom/test_component/v2', $uri);
    }

    /**
     * Test middleware retrieval for component types.
     */
    public function test_get_middleware_for_component_types(): void
    {
        $toolMiddleware = $this->patterns->getMiddleware('tools');
        $this->assertEquals(['mcp.cors', 'mcp.auth', 'mcp.validate'], $toolMiddleware);

        $resourceMiddleware = $this->patterns->getMiddleware('resources');
        $this->assertEquals(['mcp.cors', 'mcp.auth', 'mcp.cache'], $resourceMiddleware);

        $promptMiddleware = $this->patterns->getMiddleware('prompts');
        $this->assertEquals(['mcp.cors', 'mcp.auth'], $promptMiddleware);
    }

    /**
     * Test middleware retrieval with additional middleware.
     */
    public function test_get_middleware_with_additional(): void
    {
        $middleware = $this->patterns->getMiddleware('tools', ['throttle', 'custom']);
        $expected = ['mcp.cors', 'mcp.auth', 'mcp.validate', 'throttle', 'custom'];
        $this->assertEquals(sort($expected), sort($middleware));

        // Test duplicate removal
        $middleware = $this->patterns->getMiddleware('tools', ['mcp.cors', 'throttle']);
        $this->assertContains('mcp.cors', $middleware);
        $this->assertContains('mcp.auth', $middleware);
        $this->assertContains('mcp.validate', $middleware);
        $this->assertContains('throttle', $middleware);
        $this->assertCount(4, $middleware); // Should have 4 unique middleware
    }

    /**
     * Test middleware pattern retrieval.
     */
    public function test_get_middleware_pattern(): void
    {
        $this->assertEquals(['mcp.cors', 'mcp.auth', 'mcp.validate'], $this->patterns->getMiddlewarePattern('tools'));
        $this->assertEquals(['mcp.cors', 'mcp.auth', 'mcp.cache'], $this->patterns->getMiddlewarePattern('resources'));
        $this->assertEquals(['mcp.cors', 'mcp.auth'], $this->patterns->getMiddlewarePattern('prompts'));
        $this->assertEquals(['mcp.cors'], $this->patterns->getMiddlewarePattern('common'));
        $this->assertEquals(['mcp.cors', 'mcp.auth'], $this->patterns->getMiddlewarePattern('authenticated'));
        $this->assertEquals(['mcp.cors'], $this->patterns->getMiddlewarePattern('public'));
        $this->assertEquals([], $this->patterns->getMiddlewarePattern('invalid'));
    }

    /**
     * Test route constraints.
     */
    public function test_get_constraint(): void
    {
        $this->assertEquals('[a-zA-Z0-9_\-\.]+', $this->patterns->getConstraint('tool'));
        $this->assertEquals('[a-zA-Z0-9_\-\.\/]+', $this->patterns->getConstraint('resource'));
        $this->assertEquals('[a-zA-Z0-9_\-\.]+', $this->patterns->getConstraint('prompt'));
        $this->assertEquals('[0-9]+', $this->patterns->getConstraint('id'));
        $this->assertEquals('[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}', $this->patterns->getConstraint('uuid'));
        $this->assertNull($this->patterns->getConstraint('invalid'));
    }

    /**
     * Test getting all constraints.
     */
    public function test_get_all_constraints(): void
    {
        $constraints = $this->patterns->getAllConstraints();

        $this->assertIsArray($constraints);
        $this->assertArrayHasKey('tool', $constraints);
        $this->assertArrayHasKey('resource', $constraints);
        $this->assertArrayHasKey('prompt', $constraints);
        $this->assertArrayHasKey('id', $constraints);
        $this->assertArrayHasKey('uuid', $constraints);
        $this->assertCount(5, $constraints);
    }

    /**
     * Test cache configuration.
     */
    public function test_cache_configuration(): void
    {
        $this->assertTrue($this->patterns->isCacheEnabled());

        $config = $this->patterns->getCacheConfig();
        $this->assertIsArray($config);
        $this->assertTrue($config['enabled']);
        $this->assertEquals('mcp_routes', $config['key_prefix']);
        $this->assertTrue($config['cache_routes']);
        $this->assertTrue($config['cache_patterns']);
    }

    /**
     * Test cache key generation.
     */
    public function test_generate_cache_key(): void
    {
        $key = $this->patterns->generateCacheKey();
        $this->assertEquals('mcp_routes', $key);

        $key = $this->patterns->generateCacheKey('tools');
        $this->assertEquals('mcp_routes_tools', $key);

        $key = $this->patterns->generateCacheKey('patterns');
        $this->assertEquals('mcp_routes_patterns', $key);
    }

    /**
     * Test component name normalization.
     */
    public function test_normalize_component_name(): void
    {
        $this->assertEquals('calculator', $this->patterns->normalizeComponentName('Calculator'));
        $this->assertEquals('weather_service', $this->patterns->normalizeComponentName('WeatherService'));
        $this->assertEquals('user_profile_manager', $this->patterns->normalizeComponentName('User.Profile.Manager'));
        $this->assertEquals('a_p_i_v1_users', $this->patterns->normalizeComponentName('API.V1.Users'));
        $this->assertEquals('file-system-explorer', $this->patterns->normalizeComponentName('file-system-explorer'));
        $this->assertEquals('test_component', $this->patterns->normalizeComponentName('test__component'));
    }

    /**
     * Test action name normalization.
     */
    public function test_normalize_action_name(): void
    {
        $this->assertEquals('execute', $this->patterns->normalizeActionName('execute'));
        $this->assertEquals('execute_action', $this->patterns->normalizeActionName('executeAction'));
        $this->assertEquals('get_user_data', $this->patterns->normalizeActionName('getUserData'));
    }

    /**
     * Test component name denormalization.
     */
    public function test_denormalize_component_name(): void
    {
        $this->assertEquals('calculator', $this->patterns->denormalizeComponentName('calculator'));
        $this->assertEquals('weather.service', $this->patterns->denormalizeComponentName('weather_service'));
        $this->assertEquals('user.profile.manager', $this->patterns->denormalizeComponentName('user_profile_manager'));
    }

    /**
     * Test MCP route name validation.
     */
    public function test_is_mcp_route_name(): void
    {
        $this->assertTrue($this->patterns->isMcpRouteName('mcp.tools.calculator'));
        $this->assertTrue($this->patterns->isMcpRouteName('mcp.resources.user_data'));
        $this->assertTrue($this->patterns->isMcpRouteName('mcp.prompts.email_template'));
        $this->assertFalse($this->patterns->isMcpRouteName('api.tools.calculator'));
        $this->assertFalse($this->patterns->isMcpRouteName('tools.calculator'));
        $this->assertFalse($this->patterns->isMcpRouteName(''));
    }

    /**
     * Test route name parsing.
     */
    public function test_parse_route_name(): void
    {
        $parsed = $this->patterns->parseRouteName('mcp.tools.calculator');
        $this->assertEquals([
            'prefix' => 'mcp',
            'type' => 'tools',
            'name' => 'calculator',
            'action' => null,
        ], $parsed);

        $parsed = $this->patterns->parseRouteName('mcp.resources.user_data.show');
        $this->assertEquals([
            'prefix' => 'mcp',
            'type' => 'resources',
            'name' => 'user_data',
            'action' => 'show',
        ], $parsed);

        $this->assertNull($this->patterns->parseRouteName('invalid.route.name'));
        $this->assertNull($this->patterns->parseRouteName('mcp.tools'));
        $this->assertNull($this->patterns->parseRouteName(''));
    }

    /**
     * Test resource-style route generation.
     */
    public function test_generate_resource_routes(): void
    {
        $routes = $this->patterns->generateResourceRoutes('tools', 'Calculator');

        $this->assertIsArray($routes);
        $this->assertCount(4, $routes);

        // Index route
        $this->assertEquals(['GET'], $routes[0]['methods']);
        $this->assertEquals('tools', $routes[0]['uri']);
        $this->assertEquals('mcp.tools.index', $routes[0]['name']);
        $this->assertEquals('index', $routes[0]['action']);

        // Show route
        $this->assertEquals(['GET'], $routes[1]['methods']);
        $this->assertEquals('tools/calculator', $routes[1]['uri']);
        $this->assertEquals('mcp.tools.calculator.show', $routes[1]['name']);
        $this->assertEquals('show', $routes[1]['action']);

        // Store route
        $this->assertEquals(['POST'], $routes[2]['methods']);
        $this->assertEquals('tools', $routes[2]['uri']);
        $this->assertEquals('mcp.tools.store', $routes[2]['name']);
        $this->assertEquals('store', $routes[2]['action']);

        // Execute route
        $this->assertEquals(['POST'], $routes[3]['methods']);
        $this->assertEquals('tools/calculator', $routes[3]['uri']);
        $this->assertEquals('mcp.tools.calculator', $routes[3]['name']);
        $this->assertEquals('executeTool', $routes[3]['action']);
    }

    /**
     * Test HTTP methods retrieval.
     */
    public function test_get_http_methods(): void
    {
        $this->assertEquals(['POST'], $this->patterns->getHttpMethods('tools'));
        $this->assertEquals(['GET', 'POST'], $this->patterns->getHttpMethods('resources'));
        $this->assertEquals(['GET', 'POST'], $this->patterns->getHttpMethods('prompts'));
        $this->assertEquals(['GET', 'POST'], $this->patterns->getHttpMethods('invalid'));
    }

    /**
     * Test controller action retrieval.
     */
    public function test_get_controller_action(): void
    {
        $this->assertEquals('executeTool', $this->patterns->getControllerAction('tools'));
        $this->assertEquals('accessResource', $this->patterns->getControllerAction('resources'));
        $this->assertEquals('renderPrompt', $this->patterns->getControllerAction('prompts'));
        $this->assertEquals('handle', $this->patterns->getControllerAction('invalid'));
    }

    /**
     * Test setting custom patterns.
     */
    public function test_set_pattern(): void
    {
        $customPattern = [
            'prefix' => 'custom',
            'pattern' => 'custom/{item}',
            'methods' => ['GET'],
            'name_pattern' => 'mcp.custom.{name}',
            'controller_action' => 'handleCustom',
        ];

        $this->patterns->setPattern('custom_type', $customPattern);

        $pattern = $this->patterns->getPattern('custom_type');
        $this->assertEquals($customPattern, $pattern);

        // Test partial update
        $this->patterns->setPattern('custom_type', ['methods' => ['GET', 'POST']]);
        $pattern = $this->patterns->getPattern('custom_type');
        $this->assertEquals(['GET', 'POST'], $pattern['methods']);
        $this->assertEquals('custom', $pattern['prefix']); // Should remain unchanged
    }

    /**
     * Test setting middleware patterns.
     */
    public function test_set_middleware_pattern(): void
    {
        $customMiddleware = ['custom.cors', 'custom.auth'];
        $this->patterns->setMiddlewarePattern('custom', $customMiddleware);

        $middleware = $this->patterns->getMiddlewarePattern('custom');
        $this->assertEquals($customMiddleware, $middleware);
    }

    /**
     * Test setting constraints.
     */
    public function test_set_constraint(): void
    {
        $customConstraint = '[a-z]+';
        $this->patterns->setConstraint('custom_param', $customConstraint);

        $constraint = $this->patterns->getConstraint('custom_param');
        $this->assertEquals($customConstraint, $constraint);
    }

    /**
     * Test component name validation.
     */
    public function test_validate_component_name(): void
    {
        // Valid names
        $this->assertTrue($this->patterns->validateComponentName('calculator'));
        $this->assertTrue($this->patterns->validateComponentName('Calculator'));
        $this->assertTrue($this->patterns->validateComponentName('weather-service'));
        $this->assertTrue($this->patterns->validateComponentName('user.profile'));
        $this->assertTrue($this->patterns->validateComponentName('api_v1'));
        $this->assertTrue($this->patterns->validateComponentName('Component123'));

        // Invalid names
        $this->assertFalse($this->patterns->validateComponentName(''));
        $this->assertFalse($this->patterns->validateComponentName('.calculator'));
        $this->assertFalse($this->patterns->validateComponentName('calculator.'));
        $this->assertFalse($this->patterns->validateComponentName('-calculator'));
        $this->assertFalse($this->patterns->validateComponentName('calculator-'));
        $this->assertFalse($this->patterns->validateComponentName('_calculator'));
        $this->assertFalse($this->patterns->validateComponentName('calculator_'));
        $this->assertFalse($this->patterns->validateComponentName('calc@lator'));
        $this->assertFalse($this->patterns->validateComponentName('calc lator'));
        $this->assertFalse($this->patterns->validateComponentName(str_repeat('a', 101))); // Too long
    }

    /**
     * Test route template generation.
     */
    public function test_get_route_template(): void
    {
        $template = $this->patterns->getRouteTemplate('tools');

        $this->assertIsArray($template);
        $this->assertEquals(['POST'], $template['methods']);
        $this->assertEquals('tools/{tool}', $template['uri']);
        $this->assertEquals(['mcp.cors', 'mcp.auth', 'mcp.validate'], $template['middleware']);
        $this->assertEquals('executeTool', $template['action']);
        $this->assertArrayHasKey('constraints', $template);
        $this->assertEquals('[a-zA-Z0-9_\-\.]+', $template['constraints']['tool']);

        // Test invalid type
        $template = $this->patterns->getRouteTemplate('invalid');
        $this->assertEquals([], $template);
    }

    /**
     * Test route template for resources.
     */
    public function test_get_route_template_for_resources(): void
    {
        $template = $this->patterns->getRouteTemplate('resources');

        $this->assertEquals(['GET', 'POST'], $template['methods']);
        $this->assertEquals('resources/{resource}', $template['uri']);
        $this->assertEquals(['mcp.cors', 'mcp.auth', 'mcp.cache'], $template['middleware']);
        $this->assertEquals('accessResource', $template['action']);
        $this->assertEquals('[a-zA-Z0-9_\-\.\/]+', $template['constraints']['resource']);
    }

    /**
     * Test route template for prompts.
     */
    public function test_get_route_template_for_prompts(): void
    {
        $template = $this->patterns->getRouteTemplate('prompts');

        $this->assertEquals(['GET', 'POST'], $template['methods']);
        $this->assertEquals('prompts/{prompt}', $template['uri']);
        $this->assertEquals(['mcp.cors', 'mcp.auth'], $template['middleware']);
        $this->assertEquals('renderPrompt', $template['action']);
        $this->assertEquals('[a-zA-Z0-9_\-\.]+', $template['constraints']['prompt']);
    }

    /**
     * Test edge cases for route name generation.
     */
    public function test_route_name_generation_edge_cases(): void
    {
        // Empty name
        $routeName = $this->patterns->generateRouteName('tools', '');
        $this->assertEquals('mcp.tools.', $routeName);

        // Name with special characters (implementation converts dots to underscores but keeps dashes)
        $routeName = $this->patterns->generateRouteName('tools', 'test-name_with.dots');
        $this->assertEquals('mcp.tools.test-name_with_dots', $routeName);

        // Name with numbers
        $routeName = $this->patterns->generateRouteName('tools', 'Tool123Version2');
        $this->assertEquals('mcp.tools.tool123_version2', $routeName);
    }

    /**
     * Test edge cases for URI generation.
     */
    public function test_uri_generation_edge_cases(): void
    {
        // Empty name
        $uri = $this->patterns->generateRouteUri('tools', '');
        $this->assertEquals('tools/', $uri);

        // Name with special characters (implementation converts dots to underscores but keeps dashes)
        $uri = $this->patterns->generateRouteUri('tools', 'test-name_with.dots');
        $this->assertEquals('tools/test-name_with_dots', $uri);

        // Parameters that don't match pattern placeholders
        $uri = $this->patterns->generateRouteUri('tools', 'calculator', ['unknown' => 'value']);
        $this->assertEquals('tools/calculator', $uri);
    }
}

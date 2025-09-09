<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCP Server Debug Info</title>
    <style>
        body {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            background: #1a1a1a;
            color: #e0e0e0;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .header h1 {
            margin: 0;
            color: white;
            font-size: 2.5rem;
            font-weight: 300;
        }
        
        .header p {
            margin: 10px 0 0 0;
            color: rgba(255,255,255,0.8);
        }
        
        .section {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .section h2 {
            color: #4fc3f7;
            margin-top: 0;
            font-size: 1.5rem;
            border-bottom: 2px solid #4fc3f7;
            padding-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #404040;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: bold;
            color: #81c784;
        }
        
        .info-value {
            color: #e0e0e0;
            font-family: monospace;
        }
        
        .status-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-running {
            background: #4caf50;
            color: white;
        }
        
        .status-stopped {
            background: #f44336;
            color: white;
        }
        
        .status-enabled {
            background: #2196f3;
            color: white;
        }
        
        .status-disabled {
            background: #757575;
            color: white;
        }
        
        .component-list {
            display: grid;
            gap: 10px;
        }
        
        .component-item {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #4fc3f7;
        }
        
        .component-name {
            font-weight: bold;
            color: #ffb74d;
            font-size: 1.1rem;
        }
        
        .component-class {
            color: #81c784;
            font-size: 0.9rem;
            margin: 5px 0;
        }
        
        .component-description {
            color: #b0b0b0;
            font-size: 0.9rem;
        }
        
        .capability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .capability-item {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            border-top: 3px solid #9c27b0;
        }
        
        .capability-name {
            font-weight: bold;
            color: #ab47bc;
            margin-bottom: 10px;
        }
        
        .capability-config {
            font-family: monospace;
            font-size: 0.8rem;
        }
        
        .json-code {
            background: #1a1a1a;
            border: 1px solid #404040;
            border-radius: 5px;
            padding: 15px;
            overflow-x: auto;
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: white;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            border-top: 1px solid #404040;
            color: #888;
        }
        
        @media (max-width: 768px) {
            body { padding: 10px; }
            .header h1 { font-size: 2rem; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔗 MCP Server Debug Info</h1>
            <p>Laravel Model Context Protocol Server</p>
        </div>

        <!-- Server Information -->
        <div class="section">
            <h2>🖥️ Server Information</h2>
            <div class="info-grid">
                <div>
                    <div class="info-item">
                        <span class="info-label">Server Name:</span>
                        <span class="info-value">{{ $serverInfo['name'] ?? 'Laravel MCP Server' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Version:</span>
                        <span class="info-value">{{ $serverInfo['version'] ?? '1.0.0' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Protocol Version:</span>
                        <span class="info-value">{{ $serverInfo['protocolVersion'] ?? '1.0.0' }}</span>
                    </div>
                    @if(isset($serverInfo['url']))
                    <div class="info-item">
                        <span class="info-label">URL:</span>
                        <span class="info-value">{{ $serverInfo['url'] }}</span>
                    </div>
                    @endif
                </div>
                <div>
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="status-indicator {{ $serverStatus ? 'status-running' : 'status-stopped' }}">
                            {{ $serverStatus ? 'Running' : 'Stopped' }}
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Transport:</span>
                        <span class="info-value">{{ $serverInfo['transport'] ?? 'HTTP' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Laravel Version:</span>
                        <span class="info-value">{{ app()->version() }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">PHP Version:</span>
                        <span class="info-value">{{ PHP_VERSION }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Component Statistics -->
        <div class="section">
            <h2>📊 Component Statistics</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number">{{ count($components['tools'] ?? []) }}</span>
                    <span class="stat-label">Tools</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">{{ count($components['resources'] ?? []) }}</span>
                    <span class="stat-label">Resources</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">{{ count($components['prompts'] ?? []) }}</span>
                    <span class="stat-label">Prompts</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">{{ count($components['tools'] ?? []) + count($components['resources'] ?? []) + count($components['prompts'] ?? []) }}</span>
                    <span class="stat-label">Total Components</span>
                </div>
            </div>
        </div>

        <!-- Server Capabilities -->
        <div class="section">
            <h2>⚡ Server Capabilities</h2>
            <div class="capability-grid">
                @foreach($capabilities as $category => $config)
                <div class="capability-item">
                    <div class="capability-name">{{ ucfirst($category) }}</div>
                    <div class="capability-config">
                        @if(is_array($config) && !empty($config))
                            @foreach($config as $key => $value)
                                <div>
                                    {{ $key }}: 
                                    @if(is_bool($value))
                                        <span class="status-indicator {{ $value ? 'status-enabled' : 'status-disabled' }}">
                                            {{ $value ? 'Enabled' : 'Disabled' }}
                                        </span>
                                    @else
                                        <code>{{ json_encode($value) }}</code>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <span class="status-indicator {{ !empty($config) ? 'status-enabled' : 'status-disabled' }}">
                                {{ !empty($config) ? 'Enabled' : 'Disabled' }}
                            </span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <!-- Registered Tools -->
        @if(!empty($components['tools']))
        <div class="section">
            <h2>🔧 Registered Tools</h2>
            <div class="component-list">
                @foreach($components['tools'] as $name => $tool)
                <div class="component-item">
                    <div class="component-name">{{ $name }}</div>
                    <div class="component-class">{{ is_string($tool) ? $tool : get_class($tool) }}</div>
                    <div class="component-description">
                        @if(is_object($tool) && method_exists($tool, 'getDescription'))
                            {{ $tool->getDescription() }}
                        @else
                            Tool for performing specific operations
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Registered Resources -->
        @if(!empty($components['resources']))
        <div class="section">
            <h2>📄 Registered Resources</h2>
            <div class="component-list">
                @foreach($components['resources'] as $name => $resource)
                <div class="component-item">
                    <div class="component-name">{{ $name }}</div>
                    <div class="component-class">{{ is_string($resource) ? $resource : get_class($resource) }}</div>
                    <div class="component-description">
                        @if(is_object($resource) && method_exists($resource, 'getDescription'))
                            {{ $resource->getDescription() }}
                        @else
                            Resource for providing data access
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Registered Prompts -->
        @if(!empty($components['prompts']))
        <div class="section">
            <h2>💬 Registered Prompts</h2>
            <div class="component-list">
                @foreach($components['prompts'] as $name => $prompt)
                <div class="component-item">
                    <div class="component-name">{{ $name }}</div>
                    <div class="component-class">{{ is_string($prompt) ? $prompt : get_class($prompt) }}</div>
                    <div class="component-description">
                        @if(is_object($prompt) && method_exists($prompt, 'getDescription'))
                            {{ $prompt->getDescription() }}
                        @else
                            Prompt template for AI interactions
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Configuration Debug -->
        @if(isset($config) && config('app.debug'))
        <div class="section">
            <h2>⚙️ Configuration Debug</h2>
            <div class="json-code">
                <pre>{{ json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
        @endif

        <div class="footer">
            <p>Laravel MCP Package - Generated at {{ now()->format('Y-m-d H:i:s T') }}</p>
            <p>Debug mode is {{ config('app.debug') ? 'enabled' : 'disabled' }}</p>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds if server is running
        @if($serverStatus)
        setTimeout(function() {
            location.reload();
        }, 30000);
        @endif

        // Add click handlers for JSON sections to expand/collapse
        document.querySelectorAll('.json-code pre').forEach(function(element) {
            element.style.cursor = 'pointer';
            element.title = 'Click to toggle formatting';
            element.addEventListener('click', function() {
                if (this.style.whiteSpace === 'nowrap') {
                    this.style.whiteSpace = 'pre-wrap';
                    this.style.wordBreak = 'break-all';
                } else {
                    this.style.whiteSpace = 'nowrap';
                    this.style.wordBreak = 'normal';
                }
            });
        });
    </script>
</body>
</html>
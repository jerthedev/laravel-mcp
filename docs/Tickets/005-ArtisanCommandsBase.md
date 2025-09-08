# Artisan Commands Base Implementation

## Ticket Information
- **Ticket ID**: ARTISANCOMMANDS-005
- **Feature Area**: Artisan Commands Base
- **Related Spec**: [docs/Specs/04-ArtisanCommands.md](../Specs/04-ArtisanCommands.md)
- **Priority**: Medium
- **Estimated Effort**: Small (1 day)
- **Dependencies**: 004-SERVICEPROVIDERBOOT

## Summary
Implement the base command structure and core commands (mcp:serve and mcp:list) with proper Laravel command patterns.

## Requirements

### Functional Requirements
- [ ] Create base command class with shared functionality
- [ ] Implement mcp:serve command for starting MCP server
- [ ] Implement mcp:list command for listing registered components
- [ ] Add proper command signatures and descriptions
- [ ] Implement basic error handling and output formatting

### Technical Requirements
- [ ] Laravel Command class inheritance
- [ ] Proper command signature definitions
- [ ] Console output formatting
- [ ] Error handling and validation

### Laravel Integration Requirements
- [ ] Laravel console kernel integration
- [ ] Artisan command registration
- [ ] Laravel's console output methods

## Implementation Details

### Files to Create/Modify
- [ ] `src/Commands/BaseCommand.php` - Base command with shared functionality
- [ ] `src/Commands/ServeCommand.php` - MCP server start command
- [ ] `src/Commands/ListCommand.php` - Component listing command
- [ ] `src/LaravelMcpServiceProvider.php` - Register commands in boot method

### Key Classes/Interfaces
- **Main Classes**: BaseCommand, ServeCommand, ListCommand
- **Interfaces**: No new interfaces needed
- **Traits**: Console output formatting traits if needed

### Configuration
- **Config Keys**: Use existing configuration for server settings
- **Environment Variables**: MCP_DEFAULT_TRANSPORT for serve command
- **Published Assets**: No additional assets

## Testing Requirements

### Unit Tests
- [ ] Command signature tests
- [ ] Command option/argument parsing tests
- [ ] Output formatting tests

### Feature Tests
- [ ] mcp:serve command functionality
- [ ] mcp:list command output
- [ ] Command registration in Laravel

### Manual Testing
- [ ] Run mcp:serve and verify server starts
- [ ] Run mcp:list and verify component listing
- [ ] Test help output for both commands

## Acceptance Criteria
- [ ] Base command class provides shared functionality
- [ ] mcp:serve command starts MCP server correctly
- [ ] mcp:list command shows registered components
- [ ] Proper command signatures and help text
- [ ] Error handling works correctly
- [ ] Commands registered in service provider

## Definition of Done
- [ ] Base command structure implemented
- [ ] Core commands (serve, list) functional
- [ ] Commands properly registered
- [ ] All tests passing
- [ ] Command documentation complete

---

## For Implementer Use

### Development Checklist
- [ ] Branch created: `feature/artisancommands-005-base-commands`
- [ ] Base command class created
- [ ] Serve command implemented
- [ ] List command implemented
- [ ] Commands registered in service provider
- [ ] Tests written and passing
- [ ] Ready for review
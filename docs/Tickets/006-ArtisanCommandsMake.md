# Artisan Commands Make Implementation

## Ticket Information
- **Ticket ID**: ARTISANCOMMANDS-006
- **Feature Area**: Artisan Commands Make
- **Related Spec**: [docs/Specs/04-ArtisanCommands.md](../Specs/04-ArtisanCommands.md)
- **Priority**: Medium
- **Estimated Effort**: Small (1.5 days)
- **Dependencies**: 005-ARTISANCOMMANDSBASE

## Summary
Implement the code generation commands (make:mcp-tool, make:mcp-resource, make:mcp-prompt) using Laravel's stub-based generation patterns.

## Requirements

### Functional Requirements
- [ ] Implement make:mcp-tool command with stub generation
- [ ] Implement make:mcp-resource command with stub generation
- [ ] Implement make:mcp-prompt command with stub generation
- [ ] Add proper namespace resolution and file placement
- [ ] Implement stub variable replacement system
- [ ] Add validation for class names and paths

### Technical Requirements
- [ ] Laravel make command patterns
- [ ] Stub-based code generation
- [ ] File system operations with proper error handling
- [ ] Namespace and class name validation

### Laravel Integration Requirements
- [ ] Laravel filesystem integration
- [ ] Laravel stub publishing system
- [ ] Laravel naming conventions

## Implementation Details

### Files to Create/Modify
- [ ] `src/Commands/MakeToolCommand.php` - Tool generation command
- [ ] `src/Commands/MakeResourceCommand.php` - Resource generation command
- [ ] `src/Commands/MakePromptCommand.php` - Prompt generation command
- [ ] `resources/stubs/tool.stub` - Tool class template
- [ ] `resources/stubs/resource.stub` - Resource class template
- [ ] `resources/stubs/prompt.stub` - Prompt class template
- [ ] `src/LaravelMcpServiceProvider.php` - Register make commands

### Key Classes/Interfaces
- **Main Classes**: MakeToolCommand, MakeResourceCommand, MakePromptCommand
- **Interfaces**: No new interfaces needed
- **Traits**: Stub generation traits if needed

### Configuration
- **Config Keys**: Component discovery paths for placement
- **Environment Variables**: No new variables
- **Published Assets**: Stub files for customization

## Testing Requirements

### Unit Tests
- [ ] Stub generation tests
- [ ] File placement tests
- [ ] Namespace resolution tests
- [ ] Variable replacement tests

### Feature Tests
- [ ] Generated code compiles correctly
- [ ] Generated classes extend proper base classes
- [ ] File placement in correct directories

### Manual Testing
- [ ] Generate each component type and verify functionality
- [ ] Test with different naming patterns
- [ ] Verify generated code follows conventions

## Acceptance Criteria
- [ ] All three make commands generate valid code
- [ ] Generated files placed in correct locations
- [ ] Proper namespace and class naming
- [ ] Stub variable replacement working
- [ ] Generated code follows Laravel/MCP conventions
- [ ] Commands provide helpful output and error messages

## Definition of Done
- [ ] All make commands implemented and functional
- [ ] Stub templates create valid code
- [ ] File generation and placement working
- [ ] All tests passing
- [ ] Command documentation complete

---

## For Implementer Use

### Development Checklist
- [ ] Branch created: `feature/artisancommands-006-make-commands`
- [ ] Make commands implemented
- [ ] Stub templates created
- [ ] File generation logic added
- [ ] Commands registered in service provider
- [ ] Tests written and passing
- [ ] Ready for review
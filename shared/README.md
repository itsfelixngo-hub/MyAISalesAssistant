# Shared resources

Shared resources are used by more than one project:

- `plugins/ai-post-content-writer/` is the shared WordPress plugin mount.
- Put reusable themes in `themes/<theme-id>/`.
- Put reusable prompt templates and research adapters in `prompts/` and `research/`.
- Put project-specific overrides under `projects/<project-id>/`.

The shared AI worker must always include a `project_id` in future queues so one project's database/content cannot be processed under another project's configuration.

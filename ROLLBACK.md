# Rollback

Rollback restores plugin-owned state from the activation snapshot.

## Restored

- Plugin settings.
- Schema version option.
- Plugin capability assignments.
- Rewrite refresh flag.

## Preserved

Rollback never deletes:

- posts;
- pages;
- users;
- comments;
- media;
- messages;
- appointments;
- doctors;
- clinics;
- marketplace data;
- companion-plugin data.

## Reports

Rollback provides:

- preview;
- execution report;
- failure report boundary;
- manual recovery documentation through this file and the admin screen.

If no snapshot is available, rollback reports that state and stops.

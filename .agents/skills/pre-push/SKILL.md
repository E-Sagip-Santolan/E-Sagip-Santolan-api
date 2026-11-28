---
name: pre-push
description: Run the tests and check for secrets before pushing a branch.
---

1. Run php artisan test and report the result.
2. Run git status and check that no .env, vendor or key files are staged.
3. Summarize what will be pushed and suggest a commit message.
4. Do not commit or push. I will do it.
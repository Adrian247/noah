<?php

namespace Tests\Unit\Services\Reports;

use App\Services\Reports\ReportMarkdown;
use PHPUnit\Framework\TestCase;

class ReportMarkdownTest extends TestCase
{
    public function test_fenced_code_blocks_with_language_render_pre(): void
    {
        $md = <<<'MD'
Ejemplo:

```json
{"ok": true}
```

```mysql
SELECT id FROM users;
```
MD;

        $html = ReportMarkdown::toHtml($md);

        $this->assertStringContainsString('<pre>', $html);
        $this->assertStringContainsString('language-json', $html);
        $this->assertStringContainsString('language-mysql', $html);
        $this->assertStringContainsString('&quot;ok&quot;: true', $html);
        $this->assertStringContainsString('SELECT id FROM users', $html);
    }
}

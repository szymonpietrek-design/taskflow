<?php

namespace Tests\Unit\Support\Projects;

use App\Support\Projects\PullRequestBodyMarkdown;
use Tests\TestCase;

class PullRequestBodyMarkdownTest extends TestCase
{
    public function test_renders_markdown_and_dependabot_details_collapsed(): void
    {
        $html = app(PullRequestBodyMarkdown::class)->render(<<<'MARKDOWN'
### Updates

- **One**
- ~~Two~~

<details open onclick="evil()">
<summary>Release notes</summary>
<ul><li>Fixed dependency</li></ul>
</details>
MARKDOWN);

        $this->assertStringContainsString('<h3>Updates</h3>', $html);
        $this->assertStringContainsString('<strong>One</strong>', $html);
        $this->assertStringContainsString('<details>', $html);
        $this->assertStringContainsString('<summary>Release notes</summary>', $html);
        $this->assertStringContainsString('Fixed dependency', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString(' open', $html);
    }

    public function test_blocks_scripts_events_images_and_unsafe_links(): void
    {
        $html = app(PullRequestBodyMarkdown::class)->render(<<<'MARKDOWN'
<script>alert('xss')</script>
<svg onload="bad()"><script>alert('svg')</script></svg>
<img src="https://tracker.example/pixel" onerror="bad()">
<a href="javascript:alert(1)" onclick="bad()">Bad link</a>
<a href="https://github.com/example/repo/pull/1" onclick="bad()">Good link</a>
MARKDOWN);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert(', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror=', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('href="https://github.com/example/repo/pull/1"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $html);
    }

    public function test_keeps_harmless_script_examples_inside_code_fences(): void
    {
        $html = app(PullRequestBodyMarkdown::class)->render(<<<'MARKDOWN'
```html
<script>alert('example')</script>
```
MARKDOWN);

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('alert(', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_keeps_gfm_table_rendering(): void
    {
        $html = app(PullRequestBodyMarkdown::class)->render(<<<'MARKDOWN'
| Dependency | Version |
| --- | --- |
| Example | 1.2 |
MARKDOWN);

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>', $html);
        $this->assertStringContainsString('Example', $html);
    }

    public function test_empty_description_does_not_render(): void
    {
        $this->assertSame('', app(PullRequestBodyMarkdown::class)->render(null));
        $this->assertSame('', app(PullRequestBodyMarkdown::class)->render('  '));
    }
}

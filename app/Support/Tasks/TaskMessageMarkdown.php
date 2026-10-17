<?php

namespace App\Support\Tasks;

use App\Models\TaskAttachment;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Text;

/**
 * Render message Markdown and resolve attachment:ID against files explicitly
 * linked to this message. Never turn arbitrary task/library IDs into URLs.
 */
class TaskMessageMarkdown
{
    /**
     * @param iterable<TaskAttachment> $attachments
     */
    public function render(?string $markdown, int $projectId, int $taskId, iterable $attachments): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        $allowed = [];
        foreach ($attachments as $attachment) {
            if ($attachment->task_id === $taskId) {
                $allowed[(string) $attachment->id] = $attachment;
            }
        }

        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use ($allowed, $projectId, $taskId): void {
            // Collect nodes first. Replacing a node while traversing the
            // document can make the walker skip following links or images.
            $walker = $event->getDocument()->walker();
            $nodes = [];

            while ($walk = $walker->next()) {
                if (! $walk->isEntering()) {
                    continue;
                }

                $node = $walk->getNode();
                if ($node instanceof Link || $node instanceof Image) {
                    $nodes[] = $node;
                }
            }

            foreach ($nodes as $node) {
                $url = $node->getUrl();
                if (! str_starts_with(strtolower($url), 'attachment:')) {
                    continue;
                }

                if (! preg_match('/^attachment:([1-9][0-9]*)$/iD', $url, $matches)) {
                    $node->replaceWith(new Text(__('Attachment unavailable.')));
                    continue;
                }

                $attachment = $allowed[$matches[1]] ?? null;
                if ($attachment === null || ($node instanceof Image && ! $attachment->isImage())) {
                    $node->replaceWith(new Text(__('Attachment unavailable.')));
                    continue;
                }

                $routeName = $node instanceof Image ? 'tasks.attachments.preview' : 'tasks.attachments.download';
                $node->setUrl(route($routeName, [$projectId, $taskId, $attachment->id], false));
            }
        });

        return (new MarkdownConverter($environment))->convert($markdown)->getContent();
    }
}

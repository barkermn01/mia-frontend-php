<?php

namespace Marti\Frontend;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private static $converter = null;

    public static function getConverter(): MarkdownConverter
    {
        if (self::$converter === null) {
            // Configure the Environment with all the CommonMark parsers/renderers
            $environment = new Environment([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 10,
            ]);
            
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());

            self::$converter = new MarkdownConverter($environment);
        }

        return self::$converter;
    }

    public static function render(string $markdown): string
    {
        if (empty($markdown)) {
            return '';
        }

        try {
            return self::getConverter()->convert($markdown)->getContent();
        } catch (\Exception $e) {
            // Fallback to escaped plain text if markdown parsing fails
            error_log("Markdown parsing failed: " . $e->getMessage());
            return '<p>' . htmlspecialchars($markdown) . '</p>';
        }
    }

    public static function renderInline(string $markdown): string
    {
        $html = self::render($markdown);
        
        // Remove paragraph tags for inline rendering
        $html = preg_replace('/^<p>(.*)<\/p>$/s', '$1', trim($html));
        
        return $html;
    }

    public static function excerpt(string $markdown, int $length = 150): string
    {
        // Convert to plain text first
        $plainText = strip_tags(self::render($markdown));
        
        // Truncate and add ellipsis if needed
        if (strlen($plainText) > $length) {
            $plainText = substr($plainText, 0, $length);
            $plainText = substr($plainText, 0, strrpos($plainText, ' ')) . '...';
        }
        
        return $plainText;
    }
}
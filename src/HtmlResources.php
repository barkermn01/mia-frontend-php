<?php

namespace Marti\Frontend;

class HtmlResources
{
    private static $instance = null;
    private $css = [];
    private $jsHead = [];
    private $jsBody = [];
    private $metaTags = [];
    private $favicons = [];
    private $title = '';
    private $description = '';
    private $keywords = '';

    private function __construct()
    {
        // Private constructor for singleton
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Add CSS file or inline CSS
     */
    public function addCss(string $href, array $attributes = []): void
    {
        $this->css[] = [
            'href' => $href,
            'attributes' => $attributes
        ];
    }

    /**
     * Add JavaScript file to head (with defer/async support)
     */
    public function addJsHead(string $src, array $attributes = []): void
    {
        $this->jsHead[] = [
            'src' => $src,
            'attributes' => $attributes
        ];
    }

    /**
     * Add JavaScript file to end of body
     */
    public function addJsBody(string $src, array $attributes = []): void
    {
        $this->jsBody[] = [
            'src' => $src,
            'attributes' => $attributes
        ];
    }

    /**
     * Add JavaScript file (defaults to body for backward compatibility)
     */
    public function addJs(string $src, array $attributes = []): void
    {
        $this->addJsBody($src, $attributes);
    }

    /**
     * Add meta tag
     */
    public function addMeta(string $name, string $content, string $type = 'name'): void
    {
        $this->metaTags[] = [
            'type' => $type, // 'name', 'property', 'http-equiv'
            'name' => $name,
            'content' => $content
        ];
    }

    /**
     * Add favicon
     */
    public function addFavicon(string $href, string $type = 'image/x-icon', array $attributes = []): void
    {
        $this->favicons[] = [
            'href' => $href,
            'type' => $type,
            'attributes' => $attributes
        ];
    }

    /**
     * Set page title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * Set meta description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Set meta keywords
     */
    public function setKeywords(string $keywords): void
    {
        $this->keywords = $keywords;
    }

    /**
     * Get page title
     */
    public function getTitle(): string
    {
        return $this->title ?: 'Mia Storefront';
    }

    /**
     * Render all CSS links
     */
    public function renderCss(): string
    {
        $output = '';
        
        foreach ($this->css as $css) {
            $attributes = '';
            foreach ($css['attributes'] as $key => $value) {
                $attributes .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
            }
            
            $output .= '<link rel="stylesheet" href="' . htmlspecialchars($css['href']) . '"' . $attributes . '>' . "\n    ";
        }
        
        return rtrim($output);
    }

    /**
     * Render JavaScript tags for head section
     */
    public function renderJsHead(): string
    {
        $output = '';
        
        foreach ($this->jsHead as $js) {
            $attributes = '';
            foreach ($js['attributes'] as $key => $value) {
                if ($value === true) {
                    $attributes .= ' ' . htmlspecialchars($key);
                } else {
                    $attributes .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                }
            }
            
            $output .= '<script src="' . htmlspecialchars($js['src']) . '"' . $attributes . '></script>' . "\n    ";
        }
        
        return rtrim($output);
    }

    /**
     * Render JavaScript tags for body section
     */
    public function renderJsBody(): string
    {
        $output = '';
        
        foreach ($this->jsBody as $js) {
            $attributes = '';
            foreach ($js['attributes'] as $key => $value) {
                if ($value === true) {
                    $attributes .= ' ' . htmlspecialchars($key);
                } else {
                    $attributes .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                }
            }
            
            $output .= '<script src="' . htmlspecialchars($js['src']) . '"' . $attributes . '></script>' . "\n    ";
        }
        
        return rtrim($output);
    }

    /**
     * Render all JavaScript tags (backward compatibility - renders body JS)
     */
    public function renderJs(): string
    {
        return $this->renderJsBody();
    }

    /**
     * Render all meta tags
     */
    public function renderMeta(): string
    {
        $output = '';
        
        // Add default meta tags if not already set
        $hasDescription = false;
        $hasKeywords = false;
        
        foreach ($this->metaTags as $meta) {
            if ($meta['name'] === 'description') $hasDescription = true;
            if ($meta['name'] === 'keywords') $hasKeywords = true;
        }
        
        // Add description if set and not already added
        if (!$hasDescription && !empty($this->description)) {
            $output .= '<meta name="description" content="' . htmlspecialchars($this->description) . '">' . "\n    ";
        }
        
        // Add keywords if set and not already added
        if (!$hasKeywords && !empty($this->keywords)) {
            $output .= '<meta name="keywords" content="' . htmlspecialchars($this->keywords) . '">' . "\n    ";
        }
        
        // Render custom meta tags
        foreach ($this->metaTags as $meta) {
            $typeAttr = $meta['type'];
            $output .= '<meta ' . htmlspecialchars($typeAttr) . '="' . htmlspecialchars($meta['name']) . '" content="' . htmlspecialchars($meta['content']) . '">' . "\n    ";
        }
        
        return rtrim($output);
    }

    /**
     * Render all favicon links
     */
    public function renderFavicons(): string
    {
        $output = '';
        
        foreach ($this->favicons as $favicon) {
            $attributes = '';
            foreach ($favicon['attributes'] as $key => $value) {
                $attributes .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
            }
            
            $output .= '<link rel="icon" type="' . htmlspecialchars($favicon['type']) . '" href="' . htmlspecialchars($favicon['href']) . '"' . $attributes . '>' . "\n    ";
        }
        
        return rtrim($output);
    }

    /**
     * Clear all resources (useful for testing)
     */
    public function clear(): void
    {
        $this->css = [];
        $this->jsHead = [];
        $this->jsBody = [];
        $this->metaTags = [];
        $this->favicons = [];
        $this->title = '';
        $this->description = '';
        $this->keywords = '';
    }

    /**
     * Add common resources that are used on every page
     */
    public function addDefaults(): void
    {
        // Add default CSS
        $this->addCss('https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
        $this->addCss('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
        $this->addCss('/css/app.css');
        $this->addCss('/css/markdown.css');
        
        // Add default JavaScript to head (with defer for better performance)
        $this->addJsHead('https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js', ['defer' => true]);
        
        // Add default JavaScript to body (app.js needs to be after DOM and config)
        $this->addJsBody('/js/app.js');
        
        // Add default meta tags
        $this->addMeta('viewport', 'width=device-width, initial-scale=1.0');
        $this->addMeta('charset', 'UTF-8', 'charset');
    }
}
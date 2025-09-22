<?php

namespace Wpextended\Includes\Services;

/**
 * SVG Sanitization Service
 *
 * Provides secure SVG content sanitization for use across multiple modules.
 */
class SvgSanitizer
{
    /**
     * Allowed SVG tags
     *
     * @var array
     */
    private static $allowed_tags = [
        'svg', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse',
        'text', 'tspan', 'g', 'defs', 'clipPath', 'mask', 'filter', 'feGaussianBlur',
        'feColorMatrix', 'feBlend', 'feComposite', 'feFlood', 'feOffset', 'feMerge',
        'feMergeNode', 'feMorphology', 'feTile', 'feTurbulence', 'feDisplacementMap',
        'feConvolveMatrix', 'feDiffuseLighting', 'feSpecularLighting', 'feDistantLight',
        'fePointLight', 'feSpotLight', 'feImage', 'feFuncR', 'feFuncG', 'feFuncB',
        'feFuncA', 'feComponentTransfer', 'feDropShadow', 'animate', 'animateTransform',
        'animateMotion', 'set', 'use', 'symbol', 'marker', 'pattern', 'linearGradient',
        'radialGradient', 'stop', 'metadata', 'title', 'desc', 'style'
    ];

    /**
     * Allowed SVG attributes
     *
     * @var array
     */
    private static $allowed_attributes = [
        'id', 'class', 'style', 'transform', 'fill', 'fill-opacity', 'fill-rule',
        'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin',
        'stroke-dasharray', 'stroke-dashoffset', 'opacity', 'visibility', 'display',
        'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor',
        'dominant-baseline', 'alignment-baseline', 'baseline-shift', 'letter-spacing',
        'word-spacing', 'text-decoration', 'text-transform', 'direction', 'unicode-bidi',
        'writing-mode', 'text-orientation', 'glyph-orientation-horizontal',
        'glyph-orientation-vertical', 'kerning', 'color', 'color-interpolation',
        'color-interpolation-filters', 'color-rendering', 'flood-color', 'flood-opacity',
        'lighting-color', 'stop-color', 'stop-opacity', 'clip-path', 'clip-rule',
        'mask', 'filter', 'cursor', 'pointer-events', 'overflow', 'marker-start',
        'marker-mid', 'marker-end', 'markerUnits', 'markerWidth', 'markerHeight',
        'refX', 'refY', 'orient', 'patternUnits', 'patternContentUnits', 'patternTransform',
        'x', 'y', 'width', 'height', 'rx', 'ry', 'cx', 'cy', 'r', 'x1', 'y1', 'x2', 'y2',
        'points', 'd', 'pathLength', 'viewBox', 'preserveAspectRatio', 'xmlns',
        'xmlns:xlink', 'xlink:href', 'xlink:title', 'xlink:show', 'xlink:actuate',
        'xml:space', 'xml:lang', 'xml:base', 'xml:id', 'xml:class', 'xml:style',
        'xml:title', 'xml:desc', 'xml:metadata', 'xml:defs', 'xml:use', 'xml:symbol',
        'xml:marker', 'xml:pattern', 'xml:linearGradient', 'xml:radialGradient',
        'xml:stop', 'xml:animate', 'xml:animateTransform', 'xml:animateMotion',
        'xml:set', 'xml:filter', 'xml:feGaussianBlur', 'xml:feColorMatrix',
        'xml:feBlend', 'xml:feComposite', 'xml:feFlood', 'xml:feOffset',
        'xml:feMerge', 'xml:feMergeNode', 'xml:feMorphology', 'xml:feTile',
        'xml:feTurbulence', 'xml:feDisplacementMap', 'xml:feConvolveMatrix',
        'xml:feDiffuseLighting', 'xml:feSpecularLighting', 'xml:feDistantLight',
        'xml:fePointLight', 'xml:feSpotLight', 'xml:feImage', 'xml:feFuncR',
        'xml:feFuncG', 'xml:feFuncB', 'xml:feFuncA', 'xml:feComponentTransfer',
        'xml:feDropShadow'
    ];

    /**
     * Sanitize SVG content by removing dangerous elements and attributes
     *
     * @param string $content SVG content
     * @return string Sanitized SVG content
     */
    public static function sanitize($content): string
    {
        if (empty($content)) {
            return $content;
        }

        // Remove script tags and their contents
        $content = preg_replace('/<script[\s\S]*?<\/script>/i', '', $content);

        // Remove event handlers
        $content = preg_replace('/on\w+=(["\'])(?:(?=(\\\\?))\\2.)*?\\1/i', '', $content);

        // Remove style attributes with url()
        $content = preg_replace('/style=["\'].*?url\(.*?\).*?["\']/i', '', $content);

        // Remove potentially dangerous tags
        $allowed_tags_regex = implode('|', self::$allowed_tags);
        $content = preg_replace('/<(\/?)(?!' . $allowed_tags_regex . ')(\w+)[^>]*>/is', '', $content);

        // Remove foreignObject elements
        $content = preg_replace('/<foreignObject[\s\S]*?<\/foreignObject>/i', '', $content);

        // Remove external references (except data URIs for images)
        $content = preg_replace('/xlink:href=["\'](?!data:image\/svg\+xml)/i', '', $content);

        // Remove data URIs except for images
        $content = preg_replace('/data:image\/svg\+xml[^,]*,[^"]*/i', '', $content);

        // Remove potentially dangerous attributes
        $content = self::sanitizeAttributes($content);

        return $content;
    }

    /**
     * Sanitize SVG attributes
     *
     * @param string $content SVG content
     * @return string Sanitized SVG content
     */
    private static function sanitizeAttributes($content): string
    {
        // Create a regex pattern for allowed attributes
        $allowed_attrs_regex = implode('|', self::$allowed_attributes);

        // Remove attributes that are not in the allowed list
        $content = preg_replace_callback('/<([^>]+)>/', function ($matches) use ($allowed_attrs_regex) {
            $tag_content = $matches[1];

            // Extract tag name
            $tag_parts = explode(' ', trim($tag_content), 2);
            $tag_name = $tag_parts[0];

            // If there are no attributes, return as is
            if (count($tag_parts) === 1) {
                return '<' . $tag_content . '>';
            }

            $attributes = $tag_parts[1];

            // Remove dangerous attributes
            $attributes = preg_replace('/\s+(?!' . $allowed_attrs_regex . ')[a-zA-Z-]+=["\'][^"\']*["\']/i', '', $attributes);

            return '<' . $tag_name . ' ' . trim($attributes) . '>';
        }, $content);

        return $content;
    }

    /**
     * Validate SVG content
     *
     * @param string $content SVG content
     * @return bool True if valid SVG
     */
    public static function validate($content): bool
    {
        if (empty($content)) {
            return false;
        }

        // Check if it contains SVG tag
        if (strpos($content, '<svg') === false) {
            return false;
        }

        // Check for dangerous elements
        $dangerous_patterns = [
            '/<script/i',
            '/on\w+=/i',
            '/<foreignObject/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/data:text\/html/i'
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get allowed SVG tags
     *
     * @return array
     */
    public static function getAllowedTags(): array
    {
        return self::$allowed_tags;
    }

    /**
     * Get allowed SVG attributes
     *
     * @return array
     */
    public static function getAllowedAttributes(): array
    {
        return self::$allowed_attributes;
    }
}

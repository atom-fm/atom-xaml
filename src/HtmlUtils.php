<?php

namespace Atom\Xaml;

use Atom\Xaml\Component\Component;

final class HtmlUtils
{
    private static $selfClosingTags = [
        "area",
        "base",
        "br",
        "col",
        "embed",
        "hr",
        "img",
        "input",
        "link",
        "meta",
        "param",
        "source",
        "track",
        "wbr"
    ];

    public static function isSelfClosingTag($tag): bool
    {
        return in_array($tag, self::$selfClosingTags);
    }

    public static function getAttributes(Component $component): string
    {
        $result = "";
        foreach ($component->getAttributes() as $key => $value) {
            $value = htmlspecialchars($value);
            $result .= " $key=\"$value\"";
        }
        return $result;
    }
}

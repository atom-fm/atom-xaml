<?php

namespace Atom\Xaml2;

use Atom\Xaml2\Component\Component;
use Atom\Xaml2\Component\TextComponent;
use DOMDocument;
use DOMElement;
use DOMText;
use DOMXPath;
use Reflection;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

class Loader
{
    private $namespaces = [];

    public function loadXml($file)
    {
        $doc = new DOMDocument();
        $doc->loadXML(file_get_contents($file));
        $this->namespaces = $this->getNamespaces($doc);
        $result = $this->transformTree($doc->documentElement);
        return $result;
    }

    public function getComponentClassName($tag)
    {
        $parts = explode(":", $tag);
        if (count($parts) == 2) {
            $ns = $this->namespaces[$parts[0]] ?? null;
            if ($ns === null) {
                throw new RuntimeException("Namespace $ns is not defined");
            }
            $className = $parts[1];
            $className = "$ns.$className";
            $className = str_replace(".", "\\", $className);
            return $className;
        }
        $className = $parts[0];
        $className = str_replace(".", "\\", $className);
        return $className;
    }

    public function getNamespaces($doc)
    {
        $result = [];
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('namespace::*', $doc->documentElement) as $key => $node) {
            //echo print_r($node), "\n";
            $result[$node->prefix] = $node->namespaceURI;
        }
        return $result;
    }

    public function walkTree($root, $level = 1)
    {
        if ($root instanceof DOMText) {
            $content = trim($root->textContent);
            if ($content) {
                echo str_repeat(" ", $level * 4), " => ", "Text: ", trim($root->textContent), "\n";
            }
        } else {
            $attributes = [];
            foreach ($root->attributes as $key => $attribute) {
                $attributes[$key] = $attribute->nodeValue;
            }
            $data = [];

            foreach ($attributes as $key => $value) {
                $data[] = "\"$key\" => \"$value\"";
            }
            $text = implode(", ", $data);

            echo str_repeat(" ", $level * 4), " => ", "Tag: ", $root->tagName,  " [$text]", "\n";
        }

        foreach ($root->childNodes as $node) {
            $this->walkTree($node, $level + 1);
        }
    }

    private function getAttributes(DOMElement $node)
    {
        $attributes = [];
        foreach ($node->attributes as $key => $attribute) {
            $attributes[$key] = $attribute->nodeValue;
        }
        return $attributes;
    }

    public function transformTree($root, $level = 1)
    {
        $newNode = null;
        if ($root instanceof DOMText) {
            $content = trim($root->textContent);
            if ($content) {
                $newNode = new TextComponent($content);
            }
        } elseif ($root instanceof DOMElement) {
            $attributes = $this->getAttributes($root);
            $nodes = [];
            foreach ($root->childNodes as $node) {
                $newChildNode = $this->transformTree($node, $level + 1);
                if ($newChildNode) {
                    $nodes[] = $newChildNode;
                }
            }
            $tagName = $root->tagName;
            $className = $this->getComponentClassName($tagName);

            if (class_exists($className)) {
                $reflection = new ReflectionClass($className);
                $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
                $props = [];

                foreach($properties as $property) {
                    if (isset($attributes[$property->name]))
                    {
                        $value = $attributes[$property->name];
                        $props[$property->name] = $value;
                        unset($attributes[$property->name]);
                    }
                }
                $newNode = $reflection->newInstance($tagName, $attributes, $nodes);

                foreach($props as $prop => $value) {
                    $newNode->{$prop} = $value;
                }

                return $newNode;
            } else {
                $newNode = new Component($tagName, $attributes, $nodes);
            }
        }

        return $newNode;
    }

    private function renderTree($root, $level = 0)
    {
        $prefix = str_repeat(" ", $level*4);

        if ($root === null) {
            return;
        }

        if ($root instanceof TextComponent) {
            echo $prefix, trim($root->content), "\n";
            return;
        }

        $attributes = "";
        foreach ($root->getAttributes()  as $key => $value) {
            $attributes .= " $key=\"$value\"";
        }

        $tag = $root->getTag();

        if (count($root->getNodes()) > 0) {
            echo "{$prefix}<{$tag}{$attributes}>\n";
            foreach ($root->getNodes() as $node) {
                $this->renderTree($node, $level+1);
            }
            echo "{$prefix}</{$tag}>\n";
        } else {
            echo "{$prefix}<{$tag}{$attributes}></{$tag}>\n";
        }
    }

    public function render($component)
    {
        $dom = $component->render();
        $this->renderTree($dom, 0);
    }

    public function getContent($component)
    {
        ob_start();

        $this->render($component);

        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }
}

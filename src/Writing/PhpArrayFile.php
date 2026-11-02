<?php

namespace CharlesStOlive\FilamentFrontLocalization\Writing;

use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RuntimeException;

/**
 * Un fichier PHP qui rend un tableau (fichier de langue, fichier de config), modifié chirurgicalement : seule la chaîne
 * visée est remplacée, à sa place exacte. Commentaires, mise en forme, espaces insécables et appels (`env()`…) restent
 * tels quels. Seule une valeur écrite comme une chaîne simple se remplace ; une expression (concaténation, appel) est
 * laissée au développeur.
 */
final class PhpArrayFile
{
    private Parser $parser;

    public function __construct(private string $code)
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    public static function open(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("Fichier introuvable : {$path}");
        }

        return new self((string) file_get_contents($path));
    }

    public function code(): string
    {
        return $this->code;
    }

    /**
     * La chaîne au bout d'un chemin de clés (['thanks', 'title']), ou null si le chemin n'existe pas ou ne mène pas à une
     * chaîne simple.
     *
     * @param  array<int, string>  $path
     */
    public function stringAt(array $path): ?string
    {
        $node = $this->find($path);

        return $node instanceof String_ ? $node->value : null;
    }

    /**
     * Remplace la chaîne au bout du chemin. Faux si le chemin n'existe pas ou ne mène pas à une chaîne simple.
     *
     * @param  array<int, string>  $path
     */
    public function replace(array $path, string $value): bool
    {
        $node = $this->find($path);

        if (! $node instanceof String_) {
            return false;
        }

        $start = $node->getStartFilePos();
        $end = $node->getEndFilePos();
        $this->code = substr($this->code, 0, $start).self::literal($value).substr($this->code, $end + 1);

        // Le résultat doit rester du PHP valide, avec la nouvelle valeur au même endroit.
        if ($this->stringAt($path) !== $value) {
            throw new RuntimeException('La valeur réécrite ne se relit pas à l\'identique : '.implode('.', $path));
        }

        return true;
    }

    /**
     * Une chaîne PHP lisible : entre apostrophes, ou entre guillemets avec \n quand le texte a des retours à la ligne.
     */
    public static function literal(string $value): string
    {
        if (preg_match('/[\n\r\t]/', $value)) {
            return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$', "\n" => '\n', "\r" => '\r', "\t" => '\t']).'"';
        }

        return "'".strtr($value, ['\\' => '\\\\', "'" => "\\'"])."'";
    }

    /** @param  array<int, string>  $path */
    private function find(array $path): ?Expr
    {
        $node = $this->returnedArray();

        foreach ($path as $segment) {
            if (! $node instanceof Array_) {
                return null;
            }

            $node = $this->item($node, $segment)?->value;
        }

        return $node;
    }

    private function item(Array_ $array, string $key): ?ArrayItem
    {
        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem && $item->key instanceof String_ && $item->key->value === $key) {
                return $item;
            }
        }

        return null;
    }

    private function returnedArray(): ?Array_
    {
        foreach ($this->parser->parse($this->code) ?? [] as $statement) {
            if ($statement instanceof Return_ && $statement->expr instanceof Array_) {
                return $statement->expr;
            }
        }

        return null;
    }
}

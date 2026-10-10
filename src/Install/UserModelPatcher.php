<?php

namespace Melasistema\MelaCms\Install;

use Filament\Models\Contracts\FilamentUser;
use Melasistema\MelaCms\Concerns\HasCmsAccess;

/**
 * Adds the FilamentUser contract and the HasCmsAccess trait to the source of a
 * user model, keeping the imports in the alphabetical order Pint expects.
 */
final class UserModelPatcher
{
    private const string CLASS_DECLARATION = '/^((?:final\s+|abstract\s+|readonly\s+)*class\s+\w+[^{]*?)(\s*)\{/m';

    /**
     * Returns the patched source, or null when the class declaration could not
     * be found and the model has to be edited by hand.
     */
    public function patch(string $source): ?string
    {
        if (str_contains($source, 'HasCmsAccess')) {
            return $source;
        }

        if (! preg_match(self::CLASS_DECLARATION, $source, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        [$declaration, $offset] = $match[1];
        $bracePosition = $match[0][1] + strlen($match[0][0]);

        $header = substr($source, 0, $offset);
        $body = substr($source, $bracePosition);

        $header = $this->addImports($header, [FilamentUser::class, HasCmsAccess::class]);

        if ($header === null) {
            return null;
        }

        $declaration .= str_contains($declaration, ' implements ')
            ? ', FilamentUser'
            : ' implements FilamentUser';

        return $header.$declaration.$match[2][0].'{'.$this->addTrait($body);
    }

    /**
     * @param  list<class-string>  $classes
     */
    private function addImports(string $header, array $classes): ?string
    {
        $lines = explode("\n", $header);
        $imports = [];
        $position = null;
        $prefix = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^use\s+([^;]+);\s*$/', $line, $match)) {
                $imports[] = trim($match[1]);
                $position ??= $index;
                $lines[$index] = null;
            }
        }

        if ($position === null) {
            foreach ($lines as $index => $line) {
                if (str_starts_with((string) $line, 'namespace ')) {
                    $position = $index;
                    $prefix = [$line, ''];

                    break;
                }
            }

            if ($position === null) {
                return null;
            }
        }

        $imports = array_unique([...$imports, ...$classes]);
        usort($imports, strcasecmp(...));

        $lines[$position] = implode("\n", [
            ...$prefix,
            ...array_map(fn (string $import) => "use {$import};", $imports),
        ]);

        return implode("\n", array_filter($lines, fn (?string $line) => $line !== null));
    }

    /**
     * First in the class, on its own statement: where Pint's trait ordering
     * puts it in the stock model.
     */
    private function addTrait(string $body): string
    {
        if (str_starts_with(ltrim($body), '}')) {
            return "\n    use HasCmsAccess;\n".ltrim($body);
        }

        return "\n    use HasCmsAccess;\n".$body;
    }
}

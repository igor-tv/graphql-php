<?php declare(strict_types=1);

namespace GraphQL\Validator\Rules;

use function array_map;
use function count;
use GraphQL\Error\Error;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\Argument;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Utils\Utils;
use GraphQL\Validator\ValidationContext;

/**
 * Known argument names.
 *
 * A GraphQL field is only valid if all supplied arguments are defined by
 * that field.
 */
class KnownArgumentNames extends ValidationRule
{
    public function getVisitor(ValidationContext $context): array
    {
        $knownArgumentNamesOnDirectives = new KnownArgumentNamesOnDirectives();

        return $knownArgumentNamesOnDirectives->getVisitor($context) + [
            NodeKind::ARGUMENT => static function (ArgumentNode $node) use ($context): void {
                $argDef = $context->getArgument();
                if (null !== $argDef) {
                    return;
                }

                $fieldDef = $context->getFieldDef();
                if (null === $fieldDef) {
                    return;
                }

                $parentType = $context->getParentType();
                if (! $parentType instanceof NamedType) {
                    return;
                }

                $context->reportError(new Error(
                    static::unknownArgMessage(
                        $node->name->value,
                        $fieldDef->name,
                        $parentType->name,
                        Utils::suggestionList(
                            $node->name->value,
                            array_map(
                                static fn (Argument $arg): string => $arg->name,
                                $fieldDef->args
                            )
                        )
                    ),
                    [$node]
                ));
            },
        ];
    }

    /**
     * @param array<string> $suggestedArgs
     */
    public static function unknownArgMessage(string $argName, string $fieldName, string $typeName, array $suggestedArgs): string
    {
        $message = "Unknown argument \"{$argName}\" on field \"{$fieldName}\" of type \"{$typeName}\".";

        if (count($suggestedArgs) > 0) {
            $suggestions = Utils::quotedOrList($suggestedArgs);
            $message .= " Did you mean {$suggestions}?";
        }

        return $message;
    }
}

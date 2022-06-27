<?php

namespace Orolyn\Lang\ExtendedLanguage\Parser;

use PhpParser\Lexer\Emulative as BaseEmulative;

class Emulative extends BaseEmulative
{
    private int $typeArgLevel = 0;
    private bool $isDoubleTypeArgClose = false;

    public function getNextToken(&$value = null, &$startAttributes = null, &$endAttributes = null): int
    {
        if ($this->isDoubleTypeArgClose) {
            $this->isDoubleTypeArgClose = false;
            return Tokens::T_TARG_CLOSE;
        }

        $token = parent::getNextToken($value, $startAttributes, $endAttributes);

        if (ord('<') === $token) {
            $level = 0;

            for ($i = $this->pos + 1;; $i++) {
                if (!array_key_exists($i, $this->tokens)) {
                    break;
                }
                $next = $this->tokens[$i];

                if (is_array($next)) {
                    $next = $next[1];
                }

                if ('>' === $next) {
                    if (0 === $level) {
                        $this->typeArgLevel++;
                        $token = Tokens::T_TARG_START;
                        break;
                    }

                    $level--;
                    continue;
                }

                if ('>>' === $next) {
                    if ($level < 2) {
                        $this->typeArgLevel++;
                        $token = Tokens::T_TARG_START;
                        break;
                    }

                    $level -= 2;
                    continue;
                }

                if ('<' === $next) {
                    $level++;
                    continue;
                }

                if (!preg_match('/^(,|\s*|\w|\\\\)+$/', $next, $match)) {
                    break;
                }
            }
        } elseif (ord('>') === $token && $this->typeArgLevel > 0) {
            $this->typeArgLevel--;

            $token = Tokens::T_TARG_CLOSE;
        } elseif (Tokens::T_SR === $token && $this->typeArgLevel > 0) {
            $this->typeArgLevel--;
            $this->isDoubleTypeArgClose = true;

            $token = Tokens::T_TARG_CLOSE;
        }

        return $token;
    }
}

<?php

namespace Tests\Unit\Services;

use App\Services\NNTP\NNTPService;
use DariusIII\NetNntp\Error as NntpError;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

final class NNTPServiceGetXoverReturnTypeTest extends TestCase
{
    /**
     * Regression test for a production TypeError:
     *
     *   App\Services\NNTP\NNTPService::getXOVER(): Return value must be of type
     *   App\Services\NNTP\NNTPService|array|string, DariusIII\NetNntp\Error returned
     *
     * getXOVER() previously declared its return type as `array|string|NNTPService`,
     * which does not include DariusIII\NetNntp\Error. But the underlying NNTP client
     * legitimately returns an Error object whenever the server responds with an error
     * (e.g. to an XOVER command for a group with no matching articles), and every
     * caller already checks for this via NNTPService::isError($result) -- see
     * app/Services/Binaries/BinariesService.php. Because the declared return type
     * didn't permit Error, PHP threw a TypeError before the caller ever got a chance
     * to run that check, crashing the very first backfill/binaries pull that hit a
     * group boundary or empty range.
     *
     * All sibling methods on this class (doConnect, doQuit, getOverview, getGroups,
     * getMessages, getMessagesByMessageID) already use `mixed` for exactly this
     * reason. This test locks getXOVER() to the same convention so the narrow union
     * can't silently creep back in.
     */
    public function test_get_xover_return_type_permits_nntp_error(): void
    {
        $method = new ReflectionMethod(NNTPService::class, 'getXOVER');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType, 'getXOVER() must declare a return type.');

        $typeNames = $returnType instanceof ReflectionNamedType
            ? [$returnType->getName()]
            : array_map(
                fn ($type) => $type->getName(),
                method_exists($returnType, 'getTypes') ? $returnType->getTypes() : []
            );

        $isUnrestricted = in_array('mixed', $typeNames, true);
        $permitsNntpError = in_array(NntpError::class, $typeNames, true) || in_array(\Error::class, $typeNames, true);

        $this->assertTrue(
            $isUnrestricted || $permitsNntpError,
            'getXOVER() return type ('.(string) $returnType.') excludes '.NntpError::class.
            ', which the NNTP client legitimately returns on server error responses. '.
            'Every caller already guards this with NNTPService::isError(), so the '.
            'declared type must not reject it -- use `mixed`, matching every other '.
            'method on this class that can return an Error.'
        );
    }

    /**
     * Regression test for a production TypeError that failed whole batches of `update:binaries` workers:
     *
     *   NNTPService::_getTextResponse(): Return value must be of type
     *   NNTPService|array|string, DariusIII\NetNntp\Error returned
     *
     * getXOVER() calls _getTextResponse() and already checks the result with isError(), but the override
     * narrowed the parent's `mixed` return type, so a server error response threw before that check ran.
     */
    public function test_get_text_response_return_type_permits_nntp_error(): void
    {
        $returnType = (new ReflectionMethod(NNTPService::class, '_getTextResponse'))->getReturnType();

        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        $this->assertSame('mixed', $returnType->getName());
    }

    public function test_is_error_recognizes_nntp_error_instances(): void
    {
        $error = new NntpError('400 no such group', 400);

        $this->assertTrue(NNTPService::isError($error));
        $this->assertFalse(NNTPService::isError('some overview data'));
    }
}

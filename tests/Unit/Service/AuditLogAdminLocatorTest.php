<?php

declare(strict_types=1);

namespace Rcsofttech\AuditTrailBundle\Tests\Unit\Service;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\RequestContext;
use PHPUnit\Framework\TestCase;
use Rcsofttech\AuditTrailBundle\Bridge\EasyAdmin\Service\AuditLogAdminLocator;
use Rcsofttech\AuditTrailBundle\Contract\AuditLogRepositoryInterface;
use Rcsofttech\AuditTrailBundle\Entity\AuditLog;
use Rcsofttech\AuditTrailBundle\Enum\AuditAction;
use Stringable;
use Symfony\Component\HttpFoundation\Request;

final class AuditLogAdminLocatorTest extends TestCase
{
    public function testLoadFromContextUsesQueryEntityIdWhenPresent(): void
    {
        $repository = $this->createMock(AuditLogRepositoryInterface::class);
        $log = $this->createAuditLog();

        $repository->expects(self::once())
            ->method('find')
            ->with('42')
            ->willReturn($log);

        $locator = new AuditLogAdminLocator($repository);
        $context = $this->createContext(new Request(['entityId' => '42']));

        self::assertSame($log, $locator->loadFromContext($context));
    }

    public function testLoadFromContextFallsBackToAttributeEntityId(): void
    {
        $repository = $this->createMock(AuditLogRepositoryInterface::class);
        $log = $this->createAuditLog();

        $repository->expects(self::once())
            ->method('find')
            ->with('attribute-42')
            ->willReturn($log);

        $request = new Request();
        $request->attributes->set('entityId', new class implements Stringable {
            public function __toString(): string
            {
                return 'attribute-42';
            }
        });

        $locator = new AuditLogAdminLocator($repository);
        $context = $this->createContext($request);

        self::assertSame($log, $locator->loadFromContext($context));
    }

    public function testLoadFromContextReturnsNullWhenEntityIdCannotBeResolved(): void
    {
        $repository = $this->createMock(AuditLogRepositoryInterface::class);
        $repository->expects(self::never())
            ->method('find');

        $locator = new AuditLogAdminLocator($repository);

        self::assertNull($locator->loadFromContext($this->createContext()));
    }

    public function testIsUiRevertableRejectsActionsThatCannotBeRevertedFromTheUi(): void
    {
        $repository = $this->createMock(AuditLogRepositoryInterface::class);
        $repository->expects(self::never())
            ->method('isReverted');
        $repository->expects(self::never())
            ->method('hasNewerStateChangingLogs');

        $locator = new AuditLogAdminLocator($repository);

        self::assertFalse($locator->isUiRevertable($this->createAuditLog(AuditAction::Delete)));
    }

    public function testIsUiRevertableRejectsAlreadyRevertedOrSupersededLogs(): void
    {
        $repository = $this->createMock(AuditLogRepositoryInterface::class);
        $locator = new AuditLogAdminLocator($repository);
        $log = $this->createAuditLog();

        self::assertFalse($locator->isUiRevertable($log, true));

        $repository->expects(self::once())
            ->method('isReverted')
            ->with($log)
            ->willReturn(false);
        $repository->expects(self::once())
            ->method('hasNewerStateChangingLogs')
            ->with($log)
            ->willReturn(true);

        self::assertFalse($locator->isUiRevertable($log));
    }

    public function testIsUiRevertableReturnsTrueForEligibleLogs(): void
    {
        $repository = $this->createMock(AuditLogRepositoryInterface::class);
        $log = $this->createAuditLog();

        $repository->expects(self::once())
            ->method('isReverted')
            ->with($log)
            ->willReturn(false);
        $repository->expects(self::once())
            ->method('hasNewerStateChangingLogs')
            ->with($log)
            ->willReturn(false);

        $locator = new AuditLogAdminLocator($repository);

        self::assertTrue($locator->isUiRevertable($log));
    }

    public function testIsRevertedDelegatesToRepository(): void
    {
        $repository = $this->createMock(AuditLogRepositoryInterface::class);
        $log = $this->createAuditLog();

        $repository->expects(self::once())
            ->method('isReverted')
            ->with($log)
            ->willReturn(true);

        $locator = new AuditLogAdminLocator($repository);

        self::assertTrue($locator->isReverted($log));
    }

    /**
     * @return AdminContext<AuditLog>
     */
    private function createContext(?Request $request = null): AdminContext
    {
        // forTesting() binds its entity type from the CrudContext argument; these
        // tests exercise the request side only, so none is passed.
        /** @var AdminContext<AuditLog> $context */
        $context = AdminContext::forTesting(RequestContext::forTesting($request));

        return $context;
    }

    private function createAuditLog(AuditAction $action = AuditAction::Update): AuditLog
    {
        return new AuditLog(
            'App\\Entity\\Order',
            '42',
            $action,
        );
    }
}

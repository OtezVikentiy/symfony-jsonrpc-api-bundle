<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Tests\Core\Request;

use OV\JsonRPCAPIBundle\Core\Request\PartialRequestInterface;
use OV\JsonRPCAPIBundle\Core\Request\TracksProvidedFieldsTrait;
use PHPUnit\Framework\TestCase;

final class TraitConsumer implements PartialRequestInterface
{
    use TracksProvidedFieldsTrait;
}

final class TracksProvidedFieldsTraitTest extends TestCase
{
    public function testWasProvidedReturnsFalseByDefault(): void
    {
        $consumer = new TraitConsumer();

        $this->assertFalse($consumer->wasProvided('any'));
        $this->assertSame([], $consumer->getProvidedFields());
    }

    public function testMarkProvidedSetsFlag(): void
    {
        $consumer = new TraitConsumer();
        $consumer->markProvided('email');

        $this->assertTrue($consumer->wasProvided('email'));
        $this->assertFalse($consumer->wasProvided('name'));
        $this->assertSame(['email'], $consumer->getProvidedFields());
    }

    public function testMarkProvidedIsIdempotent(): void
    {
        $consumer = new TraitConsumer();
        $consumer->markProvided('email');
        $consumer->markProvided('email');
        $consumer->markProvided('email');

        $this->assertTrue($consumer->wasProvided('email'));
        $this->assertSame(['email'], $consumer->getProvidedFields());
    }

    public function testGetProvidedFieldsReturnsAllMarkedFields(): void
    {
        $consumer = new TraitConsumer();
        $consumer->markProvided('email');
        $consumer->markProvided('name');
        $consumer->markProvided('lastname');

        $this->assertEqualsCanonicalizing(
            ['email', 'name', 'lastname'],
            $consumer->getProvidedFields(),
        );
    }
}

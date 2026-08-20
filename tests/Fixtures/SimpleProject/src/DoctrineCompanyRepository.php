<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class DoctrineCompanyRepository implements CompanyRepository
{
    private const FIND_SQL = 'SELECT id FROM company WHERE id = :id';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findRequired(): array
    {
        return $this->connection->fetchAssociative(
            'SELECT id, name FROM company WHERE id = :id',
        );
    }

    public function findUsingVariableSql(): array
    {
        $sql = 'SELECT id FROM company WHERE id = :id';

        return $this->connection->fetchAssociative($sql);
    }

    public function findUsingConstantSql(): array
    {
        return $this->connection->fetchAssociative(self::FIND_SQL);
    }

    public function findRequiredFromBaseSelect(): array
    {
        $sql = $this->getBaseSelect();
        $sql .= ' WHERE id = :id';

        return $this->connection->fetchAssociative($sql);
    }

    private function getBaseSelect(): string
    {
        return <<<'SQL'
            SELECT id, name
            FROM companies c
            LEFT JOIN legal_entities le ON c.siren = le.siren
            SQL;
    }

    public function markDuplicateWithQueryBuilder(): void
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->update('companies')
            ->set('status', ':status');

        $qb->executeStatement();
    }

    public function listWithQueryBuilder(): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('c.id')
            ->from('companies', 'c');

        return $qb->executeQuery()->fetchAllAssociative();
    }

    public function deleteWithInlineQueryBuilder(): void
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->delete('companies')->executeStatement();
    }

    public function save(object $company): void
    {
        $this->connection->insert('company', [
            'id' => 1,
        ]);
    }

    public function updateStatus(): void
    {
        $this->connection->executeStatement(
            'UPDATE company SET status = :status WHERE id = :id',
        );
    }
}

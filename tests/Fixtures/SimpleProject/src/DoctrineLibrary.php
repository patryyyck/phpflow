<?php

declare(strict_types=1);

namespace App\Library;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;

// A literal table name is read as is.
#[ORM\Entity]
#[ORM\Table(name: 'library_book')]
final class Book
{
}

// Same mapping through directly imported attributes instead of the ORM alias.
#[Entity]
#[Table(name: 'library_author')]
final class Author
{
}

// Under SINGLE_TABLE, every entity of the hierarchy lives in the root table.
#[ORM\Entity]
#[ORM\Table(name: 'library_media')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['ebook' => Ebook::class, 'audiobook' => Audiobook::class, 'narrated' => NarratedAudiobook::class])]
abstract class Media
{
}

#[ORM\Entity]
class Ebook extends Media
{
}

#[ORM\Entity]
class Audiobook extends Media
{
}

// The root table is inherited through any number of levels.
#[ORM\Entity]
final class NarratedAudiobook extends Audiobook
{
}

// Under JOINED, each entity has its own table: the root table is not inherited.
#[ORM\Entity]
#[ORM\Table(name: 'library_member')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['member' => Member::class, 'staff' => StaffMember::class])]
class Member
{
}

#[ORM\Entity]
#[ORM\Table(name: 'library_staff_member')]
final class StaffMember extends Member
{
}

// No #[ORM\Table]: the name comes from the configured naming strategy, which
// is not read, so the table stays unresolved.
#[ORM\Entity]
final class ReadingList
{
}

// #[ORM\Table] without a name leaves the naming strategy in charge as well.
#[ORM\Entity]
#[ORM\Table(options: ['comment' => 'Open loans'])]
final class Loan
{
}

// Only a literal name is read.
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class Reservation
{
    public const TABLE = 'library_reservation';
}

// Not an entity: a table attribute alone maps nothing.
#[ORM\Table(name: 'library_shelf')]
final class Shelf
{
}

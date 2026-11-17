<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'comment')]
class Comment
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\ManyToOne(targetEntity: BlogPost::class, inversedBy: 'comments')]
    #[ORM\JoinColumn(name: 'blog_post_id', referencedColumnName: 'id')]
    public BlogPost $post;

    #[ORM\Column(type: 'string')]
    public string $body;

    public function __construct(int $id, BlogPost $post, string $body)
    {
        $this->id = $id;
        $this->post = $post;
        $this->body = $body;
    }
}

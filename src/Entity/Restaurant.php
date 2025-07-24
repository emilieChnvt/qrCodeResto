<?php

namespace App\Entity;

use App\Repository\RestaurantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]

#[ORM\Entity(repositoryClass: RestaurantRepository::class)]
class Restaurant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\ManyToOne(inversedBy: 'restaurants')]
    private ?User $ofUser = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createAt = null;

    /**
     * @var Collection<int, MenuCategory>
     */
    #[ORM\OneToMany(targetEntity: MenuCategory::class, mappedBy: 'restaurant', orphanRemoval: true)]
    private Collection $menuCategories;


    #[Vich\UploadableField(mapping: 'menu_files', fileNameProperty: 'menuFilename')]
    private ?File $menuFile = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $menuFileName = null;

    /**
     * @var Collection<int, Menu>
     */
    #[ORM\OneToMany(targetEntity: Menu::class, mappedBy: 'restaurant', orphanRemoval: true)]
    private Collection $menus;

    public function __construct()
    {
        $this->menuCategories = new ArrayCollection();
        $this->menus = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getOfUser(): ?User
    {
        return $this->ofUser;
    }

    public function setOfUser(?User $ofUser): static
    {
        $this->ofUser = $ofUser;

        return $this;
    }

    public function getCreateAt(): ?\DateTimeImmutable
    {
        return $this->createAt;
    }

    public function setCreateAt(\DateTimeImmutable $createAt): static
    {
        $this->createAt = $createAt;

        return $this;
    }

    /**
     * @return Collection<int, MenuCategory>
     */
    public function getMenuCategories(): Collection
    {
        return $this->menuCategories;
    }

    public function addMenuCategory(MenuCategory $menuCategory): static
    {
        if (!$this->menuCategories->contains($menuCategory)) {
            $this->menuCategories->add($menuCategory);
            $menuCategory->setRestaurant($this);
        }

        return $this;
    }

    public function removeMenuCategory(MenuCategory $menuCategory): static
    {
        if ($this->menuCategories->removeElement($menuCategory)) {
            // set the owning side to null (unless already changed)
            if ($menuCategory->getRestaurant() === $this) {
                $menuCategory->setRestaurant(null);
            }
        }

        return $this;
    }

    public function getMenuFileName(): ?string
    {
        return $this->menuFileName;
    }

    public function setMenuFileName(?string $menuFileName): static
    {
        $this->menuFileName = $menuFileName;

        return $this;
    }
    public function setMenuFile(?File $menuFile = null): void
    {
        $this->menuFile = $menuFile;

        if (null !== $menuFile) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->createAt = new \DateTimeImmutable();
        }
    }

    public function getMenuFile(): ?File
    {
        return $this->menuFile;
    }

    /**
     * @return Collection<int, Menu>
     */
    public function getMenus(): Collection
    {
        return $this->menus;
    }

    public function addMenu(Menu $menu): static
    {
        if (!$this->menus->contains($menu)) {
            $this->menus->add($menu);
            $menu->setRestaurant($this);
        }

        return $this;
    }

    public function removeMenu(Menu $menu): static
    {
        if ($this->menus->removeElement($menu)) {
            // set the owning side to null (unless already changed)
            if ($menu->getRestaurant() === $this) {
                $menu->setRestaurant(null);
            }
        }

        return $this;
    }

}

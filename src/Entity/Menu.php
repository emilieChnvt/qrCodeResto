<?php

namespace App\Entity;

use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'menus')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Restaurant $restaurant = null;

    /**
     * @var Collection<int, MenuCategory>
     */
    #[ORM\ManyToMany(targetEntity: MenuCategory::class, inversedBy: 'menus')]
    private Collection $categories;

    /**
     * @var Collection<int, MenuItem>
     */
    #[ORM\ManyToMany(targetEntity: MenuItem::class, inversedBy: 'menus')]
    private Collection $menuItems;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->menuItems = new ArrayCollection();
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

    public function getRestaurant(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function setRestaurant(?Restaurant $restaurant): static
    {
        $this->restaurant = $restaurant;

        return $this;
    }

    /**
     * @return Collection<int, MenuCategory>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(MenuCategory $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->addMenu($this);
        }

        return $this;
    }


    public function removeCategory(MenuCategory $category): static
    {
        if ($this->categories->removeElement($category)) {
            $category->removeMenu($this);
        }

        return $this;
    }


    public function setCategories(iterable $categories): self
    {
        // Vide la collection actuelle
        $this->categories->clear();

        // Ajoute chaque catégorie une par une (utilise addCategory pour éviter doublons)
        foreach ($categories as $category) {
            $this->addCategory($category);
        }

        return $this;
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function getMenuItems(): Collection
    {
        return $this->menuItems;
    }

    public function addMenuItem(MenuItem $menuItem): static
    {
        if (!$this->menuItems->contains($menuItem)) {
            $this->menuItems->add($menuItem);
        }

        return $this;
    }

    public function removeMenuItem(MenuItem $menuItem): static
    {
        $this->menuItems->removeElement($menuItem);

        return $this;
    }

}

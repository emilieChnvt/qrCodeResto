<?php

namespace App\Repository;

use App\Entity\MenuCategory;
use App\Entity\Restaurant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuCategory>
 */
class MenuCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuCategory::class);
    }
    public function findByRestaurant(Restaurant $restaurant):array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant)
            ->leftJoin('c.menuItems', 'm')
            ->addSelect('m')
            ->getQuery()
            ->getResult();

    }

    //    /**
    //     * @return MenuCategory[] Returns an array of MenuCategory objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?MenuCategory
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

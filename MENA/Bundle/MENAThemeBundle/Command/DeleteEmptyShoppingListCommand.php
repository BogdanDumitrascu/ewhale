<?php

namespace MENA\Bundle\MENAThemeBundle\Command;

use Doctrine\ORM\EntityRepository;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Oro\Bundle\CronBundle\Command\CronCommandInterface;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;

class DeleteEmptyShoppingListCommand extends ContainerAwareCommand implements CronCommandInterface
{
    const NAME = 'oro:cron:shopping-list:delete-empty';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName(self::NAME)
            ->setDescription('Delete empty guest shopping lists.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ids = $this->getEmptyShoppingListIds();

        if ($ids) {
            $registry = $this->getContainer()->get('doctrine');
            $manager = $registry->getManagerForClass(ShoppingList::class);
            /** @var EntityRepository $repository */
            $repository = $manager->getRepository(ShoppingList::class);

            $qb = $repository->createQueryBuilder('sl');
            $qb->delete(ShoppingList::class, 'sl');
            $qb->where($qb->expr()->in('sl.id', ':ids'));
            $qb->setParameter('ids', $ids);

            $qb->getQuery()->execute();
        }

        $output->writeln('<info>Deleted '.sizeof($ids).' empty guest shopping lists completed</info>');
    }

    /**
     * @return array
     */
    private function getEmptyShoppingListIds()
    {
        $registry = $this->getContainer()->get('doctrine');
        $manager = $registry->getManagerForClass(ShoppingList::class);
        /** @var EntityRepository $repository */
        $repository = $manager->getRepository(ShoppingList::class);

        $qb = $repository->createQueryBuilder('sl');
        $qb->select(['sl.id'])
            ->LeftJoin('sl.lineItems', 'items')
            ->where('items.id is NULL');

        $ids = [];
        foreach ($qb->getQuery()->getArrayResult() as $item) {
            $ids[] = $item['id'];
        }

        return $ids;
    }

    /**
     * {@inheritdoc}
     * every 5 minutes
     */
    public function getDefaultDefinition()
    {
        return '*/5 * * * *';
    }

    /**
     * {@inheritdoc}
     */
    public function isActive()
    {
        return true;
    }
}

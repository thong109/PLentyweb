<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Repository;

use Eccube\Entity\BlockPosition;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * BlockPositionRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class BlockPositionRepository extends AbstractRepository
{
    /**
     * @var BlockRepository
     */
    protected $blockRepository;

    /**
     * BlockPositionRepository constructor.
     */
    public function __construct(BlockRepository $blockRepository, RegistryInterface $registry)
    {
        parent::__construct($registry, BlockPosition::class);
        $this->blockRepository = $blockRepository;
    }

    /**
     * レイアウトに紐づくブロックの個数分登録を行う
     *
     * @param  array|null $data
     * @param  $Blocks
     * @param  $UnusedBlocks
     * @param  Eccube\Entity\Layout|null $Layout
     */
    public function register($data, $Blocks, $UnusedBlocks, $Layout)
    {
        $em = $this->getEntityManager();

        $max = count($Blocks) + count($UnusedBlocks);
        for ($i = 0; $i < $max; $i++) {
            // block_idが取得できない場合はinsertしない
            if (!isset($data['block_id_'.$i])) {
                continue;
            }
            // 未使用ブロックはinsertしない
            if ($data['section_'.$i] == \Eccube\Entity\Layout::TARGET_ID_UNUSED) {
                continue;
            }
            $Block = $this->blockRepository->find($data['block_id_'.$i]);
            $BlockPosition = new BlockPosition();
            $BlockPosition
                ->setBlockId($data['block_id_'.$i])
                ->setLayoutId($Layout->getId())
                ->setBlockRow($data['block_row_'.$i])
                ->setSection($data['section_'.$i])
                ->setBlock($Block)
                ->setLayout($Layout);
            $Layout->addBlockPosition($BlockPosition);
            $em->persist($BlockPosition);
            $em->flush($BlockPosition);
        }
    }
}

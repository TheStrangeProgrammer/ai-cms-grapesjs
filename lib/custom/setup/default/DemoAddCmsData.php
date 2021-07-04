<?php

/**
 * @license LGPLv3, https://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2021
 */


namespace Aimeos\MW\Setup\Task;


/**
 * Adds demo records to product tables.
 */
class DemoAddCmsData extends \Aimeos\MW\Setup\Task\MShopAddDataAbstract
{
	/**
	 * Returns the list of task names which this task depends on.
	 *
	 * @return string[] List of task names
	 */
	public function getPreDependencies() : array
	{
		return ['MShopAddTypeDataCms', 'DemoAddTypeData'];
	}


	/**
	 * Returns the list of task names which depends on this task.
	 *
	 * @return string[] List of task names
	 */
	public function getPostDependencies() : array
	{
		return ['DemoRebuildIndex'];
	}


	/**
	 * Insert product data.
	 */
	public function migrate()
	{
		$this->msg( 'Processing CMS demo data', 0 );

		$context = $this->getContext();
		$value = $context->getConfig()->get( 'setup/default/demo', '' );

		if( $value === '' )
		{
			$this->status( 'OK' );
			return;
		}


		$domains = ['media', 'text'];
		$manager = \Aimeos\MShop::create( $context, 'cms' );

		$search = $manager->filter();
		$search->setConditions( $search->compare( '=~', 'cms.label', 'Demo ' ) );
		$pages = $manager->search( $search, $domains );

		foreach( $domains as $domain )
		{
			$rmIds = map();

			foreach( $pages as $item ) {
				$rmIds = $rmIds->merge( $item->getRefItems( $domain, null, null, false )->keys() );
			}

			\Aimeos\MShop::create( $context, $domain )->delete( $rmIds->toArray() );
		}

		$manager->delete( $pages->toArray() );


		if( $value === '1' )
		{
			$this->addDemoData();
			$this->status( 'added' );
		}
		else
		{
			$this->status( 'removed' );
		}
	}


	/**
	 * Adds the demo data to the database.
	 *
	 * @throws \Aimeos\MShop\Exception If the file isn't found
	 */
	protected function addDemoData()
	{
		$ds = DIRECTORY_SEPARATOR;
		$path = __DIR__ . $ds . 'data' . $ds . 'demo-cms.php';

		if( ( $data = include( $path ) ) == false ) {
			throw new \Aimeos\MShop\Exception( sprintf( 'No file "%1$s" found for CMS domain', $path ) );
		}

		$context = $this->getContext();
		$manager = \Aimeos\MShop::create( $context, 'cms' );

		foreach( $data as $entry )
		{
			$item = $manager->create()->fromArray( $entry );
			$this->addRefItems( $item, $entry );
			$manager->save( $item );
		}
	}


	/**
	 * Adds the referenced items from the given entry data.
	 *
	 * @param \Aimeos\MShop\Common\Item\ListsRef\Iface $item Item with list items
	 * @param array $entry Associative list of data with stock, attribute, media, price, text and product sections
	 * @return \Aimeos\MShop\Common\Item\ListsRef\Iface $item Updated item
	 */
	protected function addRefItems( \Aimeos\MShop\Common\Item\ListsRef\Iface $item, array $entry )
	{
		$context = $this->getContext();
		$domain = $item->getResourceType();
		$listManager = \Aimeos\MShop::create( $context, $domain . '/lists' );

		foreach( ['media', 'text'] as $refDomain )
		{
			if( isset( $entry[$refDomain] ) )
			{
				$manager = \Aimeos\MShop::create( $context, $refDomain );

				foreach( $entry[$refDomain] as $data )
				{
					$listItem = $listManager->create()->fromArray( $data );
					$refItem = $manager->create()->fromArray( $data );

					$item->addListItem( $refDomain, $listItem, $refItem );
				}
			}
		}

		return $item;
	}
}

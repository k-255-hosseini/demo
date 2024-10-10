<?php
/**
 * Free gifts table.
 *
 * @var string $wrapper_classes String with wrapper classes.
 * @var array  $data Data for render table.
 *
 * @package Woodmart
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use XTS\Modules\Free_Gifts\Manager;
?>

<?php do_action( 'woodmart_before_free_gifts_table' ); ?>

<div class="wd-fg<?php echo esc_attr( $wrapper_classes ); ?>">
	<h4 class="title wd-el-title">
		<?php echo esc_html( _n( 'Choose your gift', 'Choose your gifts', count( $data ), 'woodmart' ) ); ?>
	</h4>

	<table class="wd-fg-table shop_table shop_table_responsive shop-table-with-img">
		<tbody>
		<?php foreach ( $data as $id => $free_gift_id ) : ?>
			<?php
				$free_gift_product = wc_get_product( $free_gift_id );
				$product_permalink = apply_filters( 'woodmart_free_gift_item_permalink', $free_gift_product->is_visible() ? $free_gift_product->get_permalink() : '', $free_gift_id );
				$product_name      = apply_filters( 'woodmart_free_gift_item_name', $free_gift_product->get_name(), $free_gift_id );
			?>
			<tr>
				<td class="product-thumbnail">
					<?php
					if ( ! $product_permalink ) {
						echo apply_filters( 'woodmart_free_gift_item_thumbnail', $free_gift_product->get_image(), $free_gift_id );
					} else {
						printf( '<a href="%s">%s</a>', esc_url( $product_permalink ), apply_filters( 'woodmart_free_gift_item_thumbnail', $free_gift_product->get_image(), $free_gift_id ) );
					}
					?>
				</td>
				<td class="product-name" data-title="<?php esc_attr_e( 'Product', 'woodmart' ); ?>">
					<?php
					if ( ! $product_permalink ) {
						echo wp_kses_post( $product_name . '&nbsp;' );
					} else {
						/**
						 * This filter is documented above.
						 *
						 * @since 7.8.0
						 * @param string $product_url URL the product in the cart.
						 */
						echo wp_kses_post( apply_filters( 'woodmart_free_gift_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $free_gift_product->get_name() ), $free_gift_id ) );
					}

					if ( woodmart_get_opt( 'show_sku_in_cart' ) ) {
						?>
						<div class="wd-product-sku">
							<span class="wd-label">
								<?php esc_html_e( 'SKU:', 'woodmart' ); ?>
							</span>
							<span>
								<?php if ( $free_gift_product->get_sku() ) : ?>
									<?php echo esc_html( $free_gift_product->get_sku() ); ?>
								<?php else : ?>
									<?php esc_html_e( 'N/A', 'woodmart' ); ?>
								<?php endif; ?>
							</span>
						</div>
						<?php
					}
					?>
				</td>
				<td class="product-btn">
					<a class="button wd-add-gift-product<?php echo Manager::get_instance()->check_is_gift_in_cart( $free_gift_id ) ? ' wd-disabled' : ''; ?>" data-product-id="<?php echo esc_attr( $free_gift_id ); ?>" data-security="<?php echo esc_attr( wp_create_nonce( 'wd_free_gift_' . $free_gift_id ) ); ?>" href="#">
						<?php echo esc_html__( 'Add to cart', 'woodmart' ); ?>
					</a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="wd-loader-overlay wd-fill"></div>
</div>

<?php do_action( 'woodmart_after_free_gifts_table' ); ?>

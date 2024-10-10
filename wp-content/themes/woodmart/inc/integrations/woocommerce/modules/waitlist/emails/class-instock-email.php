<?php
/**
 * Send promotional email.
 *
 * @package XTS
 */

namespace XTS\Modules\Waitlist\Emails;

use XTS\Modules\Waitlist\DB_Storage;
use WP_User;
use WC_Product;

/**
 * Send back in stock status product for waitlist subscribers.
 */
class Instock_Email extends Waitlist_Email {
	/**
	 * Create an instance of the class.
	 */
	public function __construct() {
		$this->id          = 'woodmart_waitlist_in_stock';
		$this->title       = esc_html__( 'Waitlist - Product back in stock', 'woodmart' );
		$this->description = esc_html__( 'Set up the email notification that informs customers when a product they have been waiting for is back in stock.', 'woodmart' );

		$this->customer_email = true;
		$this->heading        = esc_html__( 'Good news! The product you\'ve been waiting for is now back in stock.', 'woodmart' );
		$this->subject        = esc_html__( 'A product you are waiting for is back in stock', 'woodmart' );

		$this->template_html  = 'emails/waitlist-in-stock.php';
		$this->template_plain = 'emails/plain/waitlist-in-stock.php';

		add_action( 'woodmart_waitlist_send_in_stock_notification', array( $this, 'trigger' ) );

		parent::__construct();
	}

	/**
	 * Trigger Function that will send this email to the customer.
	 *
	 * @param stdClass[] $waitlists List of subscribers data.
	 *
	 * @return void
	 */
	public function trigger( $waitlists ) {
		foreach ( $waitlists as $waitlist ) {
			$product_id      = $waitlist->variation_id ? $waitlist->variation_id : $waitlist->product_id;
			$this->object    = wc_get_product( $product_id );
			$this->recipient = $waitlist->user_email;

			if ( ! $this->is_enabled() || ! $this->get_recipient() || ! $this->object ) {
				return;
			}

			$this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->get_attachments()
			);

			$this->db_storage->unsubscribe_by_token( $waitlist->unsubscribe_token );
		}
	}

	/**
	 * Returns default email content.
	 *
	 * @param string $email_type Email type.
	 *
	 * @return string Default content.
	 */
	public function get_default_content( $email_type ) {
		if ( 'plain' === $email_type ) {
			return __(
				'Hi {user_name},
Great news! The {product_title} ({product_link}) on your waitlist is now back in stock!
Since you requested to be notified, we wanted to make sure you\'re the first to know. However, we can\'t guarantee how long it will be available.
Click the link below to grab it before it\'s gone!
{product_title} {product_price} {add_to_cart_url}

Best regards,
{site_title}',
				'woodmart'
			);
		} else {
			return __(
				'<p>Hi {user_name}</p>
<p>Great news! The {product_title} ({product_link}) on your waitlist is now back in stock!</p>
<p>Since you requested to be notified, we wanted to make sure you\'re the first to know. However, we can\'t guarantee how long it will be available.</p>
<p>Click the link below to grab it before it\'s gone!</p>
<table class="td xts-prod-table" cellspacing="0" cellpadding="6" border="1">
	<thead>
		<tr>
			<th class="td" scope="col"></th>
			<th class="td xts-align-start" scope="col">Product</th>
			<th class="td xts-align-start" scope="col">Price</th>
			<th class="td xts-align-end" scope="col">Add to cart</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td class="td xts-tbody-td xts-img-col xts-align-start">
				<a href="{product_link}">
					{product_image}
				</a>
			</td>
			<td class="td xts-tbody-td xts-align-start">
				{product_title_with_link}
			</td>
			<td class="td xts-tbody-td xts-align-start">
				{product_price}
			</td>
			<td class="td xts-tbody-td xts-align-end">
				<a href="{add_to_cart_url}" class="xts-add-to-cart">
					Add to cart
				</a>
			</td>
		</tr>
	</tbody>
</table>',
				'woodmart'
			);
		}
	}
}

return new Instock_Email();

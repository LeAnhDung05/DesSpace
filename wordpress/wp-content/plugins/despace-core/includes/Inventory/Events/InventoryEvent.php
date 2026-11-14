<?php
/**
 * Standardized Inventory Event DTO.
 *
 * @package DeSpace\Inventory\Events
 */

namespace DeSpace\Inventory\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Class InventoryEvent
 *
 * Represents a single validated stock change event before it is persisted.
 */
class InventoryEvent {

	/**
	 * Event Types.
	 */
	const TYPE_ORDER_REDUCTION   = 'order_reduction';
	const TYPE_STOCK_RESTORATION = 'stock_restoration';
	const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';
	const TYPE_REFUND_RESTOCK    = 'refund_restock';
	const TYPE_IMPORT            = 'import';
	const TYPE_SYSTEM            = 'system';

	/**
	 * Event Sources.
	 */
	const SOURCE_ORDER    = 'order';
	const SOURCE_REFUND   = 'refund';
	const SOURCE_ADMIN    = 'admin';
	const SOURCE_REST_API = 'rest_api';
	const SOURCE_IMPORT   = 'import';
	const SOURCE_SYSTEM   = 'system';

	/**
	 * Event properties.
	 */
	private ?string $event_key;
	private string $event_type;
	private string $event_source;
	private int $product_id;
	private ?int $variation_id;
	private ?int $order_id;
	private ?int $order_item_id;
	private ?int $refund_id;
	private ?int $actor_user_id;
	private ?float $quantity_before;
	private float $quantity_delta;
	private ?float $stock_after;
	private string $created_at;
	private array $context;

	/**
	 * Constructor.
	 *
	 * @param array $data Event data.
	 */
	public function __construct( array $data = array() ) {
		$this->event_key       = isset( $data['event_key'] ) ? sanitize_text_field( $data['event_key'] ) : null;
		$this->event_type      = isset( $data['event_type'] ) ? sanitize_key( $data['event_type'] ) : self::TYPE_MANUAL_ADJUSTMENT;
		$this->event_source    = isset( $data['event_source'] ) ? sanitize_key( $data['event_source'] ) : self::SOURCE_SYSTEM;
		$this->product_id      = isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
		$this->variation_id    = ! empty( $data['variation_id'] ) ? absint( $data['variation_id'] ) : null;
		$this->order_id        = ! empty( $data['order_id'] ) ? absint( $data['order_id'] ) : null;
		$this->order_item_id   = ! empty( $data['order_item_id'] ) ? absint( $data['order_item_id'] ) : null;
		$this->refund_id       = ! empty( $data['refund_id'] ) ? absint( $data['refund_id'] ) : null;
		$this->actor_user_id   = ! empty( $data['actor_user_id'] ) ? absint( $data['actor_user_id'] ) : null;
		$this->quantity_before = isset( $data['quantity_before'] ) && null !== $data['quantity_before'] ? (float) $data['quantity_before'] : null;
		$this->quantity_delta  = isset( $data['quantity_delta'] ) ? (float) $data['quantity_delta'] : 0.0;
		$this->stock_after     = isset( $data['stock_after'] ) && null !== $data['stock_after'] ? (float) $data['stock_after'] : null;
		$this->created_at      = ! empty( $data['created_at'] ) ? $data['created_at'] : gmdate( 'Y-m-d H:i:s' );
		$this->context         = isset( $data['context'] ) && is_array( $data['context'] ) ? $data['context'] : array();

		// Auto calculate quantity_delta if not explicitly passed but before and after exist.
		if ( 0.0 === $this->quantity_delta && null !== $this->quantity_before && null !== $this->stock_after ) {
			$this->quantity_delta = $this->stock_after - $this->quantity_before;
		}
	}

	/**
	 * Validate event data.
	 *
	 * @return bool True if valid for recording.
	 */
	public function is_valid(): bool {
		// Product ID is required.
		if ( $this->product_id <= 0 ) {
			return false;
		}

		// Quantity delta must be non-zero (delta = 0 means no actual inventory movement).
		if ( abs( $this->quantity_delta ) < 0.000001 ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate an idempotent event key if not already assigned.
	 *
	 * @param string $source_operation_id Unique identifier from source (order_id, refund_id, or request uuid).
	 * @return string SHA256 64-char event key.
	 */
	public function build_event_key( string $source_operation_id = '' ): string {
		if ( ! empty( $this->event_key ) ) {
			return $this->event_key;
		}

		$parts = array(
			$this->event_source,
			$this->event_type,
			(string) $this->product_id,
			(string) ( $this->variation_id ?? 0 ),
			(string) ( $this->order_id ?? 0 ),
			(string) ( $this->order_item_id ?? 0 ),
			(string) ( $this->refund_id ?? 0 ),
		);

		if ( ! empty( $source_operation_id ) ) {
			$parts[] = $source_operation_id;
		}

		$this->event_key = hash( 'sha256', implode( ':', $parts ) );
		return $this->event_key;
	}

	/**
	 * Convert to database insert array.
	 *
	 * @return array
	 */
	public function to_db_array(): array {
		return array(
			'event_key'       => $this->event_key ?? $this->build_event_key(),
			'event_type'      => $this->event_type,
			'event_source'    => $this->event_source,
			'product_id'      => $this->product_id,
			'variation_id'    => $this->variation_id,
			'order_id'        => $this->order_id,
			'order_item_id'   => $this->order_item_id,
			'refund_id'       => $this->refund_id,
			'actor_user_id'   => $this->actor_user_id,
			'quantity_before' => $this->quantity_before,
			'quantity_delta'  => $this->quantity_delta,
			'stock_after'     => $this->stock_after,
			'created_at'      => $this->created_at,
			'context_json'    => ! empty( $this->context ) ? wp_json_encode( $this->context ) : null,
		);
	}

	// Getters.
	public function get_event_key(): ?string { return $this->event_key; }
	public function get_event_type(): string { return $this->event_type; }
	public function get_event_source(): string { return $this->event_source; }
	public function get_product_id(): int { return $this->product_id; }
	public function get_variation_id(): ?int { return $this->variation_id; }
	public function get_order_id(): ?int { return $this->order_id; }
	public function get_order_item_id(): ?int { return $this->order_item_id; }
	public function get_refund_id(): ?int { return $this->refund_id; }
	public function get_actor_user_id(): ?int { return $this->actor_user_id; }
	public function get_quantity_before(): ?float { return $this->quantity_before; }
	public function get_quantity_delta(): float { return $this->quantity_delta; }
	public function get_stock_after(): ?float { return $this->stock_after; }
	public function get_created_at(): string { return $this->created_at; }
	public function get_context(): array { return $this->context; }
}


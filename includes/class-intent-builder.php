<?php

namespace ONVO;

class Intent_Builder {
	public static function from_get_intent_response( array $api_intent ): Intent {
		if ( ! isset( $api_intent['id'] ) || is_null( trim( $api_intent['id'] ) ) ) {
			error( sprintf( 'Invalid intent id: %s. Error: %s', $api_intent['id'], $api_intent['message'] ), [
				'intent_id' => $api_intent['id'],
				'method'    => __METHOD__,
				'message'   => $api_intent['message'],
				'path'      => $api_intent['path']
			] );

			if ( isset( $api_intent['message'] ) ) {
				$message = $api_intent['message'];
				if ( is_array( $message ) ) {
					$message = wp_json_encode( $message );
				}

				throw new \Exception( $message );
			}

			throw new \Exception( sprintf( 'Invalid intent id: %s', $api_intent['id'] ) );
		}

		$intent = new Intent();
		$intent->set_id( $api_intent['id'] );

		if ( isset( $api_intent['charges'] ) ) {
			$intent->set_charges( $api_intent['charges'] );
		}

		if ( Currency::isValid( $api_intent['currency'] ) ) {
			$intent->set_currency( Currency::from( $api_intent['currency'] ) );
		}

		$intent->set_customer_id( self::get_customer_id( $api_intent ) );
		$intent->set_payment_method_id( self::get_payment_method_id( $api_intent ) );
		$intent->set_amount( $api_intent['amount'] );
		$intent->set_status( $api_intent['status'] );

		return $intent;
	}

	public static function from_cart( \WC_Cart $cart, $currency, $customer_id ): Intent {
		$intent = new Intent();

		$intent->set_amount( wc_number_to_onvo( $cart->get_total( false ) ) );
		$intent->set_description( sprintf( 'Intent para carrito' ) );

		if ( Currency::isValid( $currency ) ) {
			$intent->set_currency( Currency::from( $currency ) );
		}

		if ( $customer_id ) {
			$intent->set_customer_id( $customer_id );
		}

		if ( get_intent_id_from_wc_session() ) {
			$intent->set_id( get_intent_id_from_wc_session() );
		}

		return $intent;
	}

	public static function from_renewal( \WC_Order $order, string $onvo_customer_id ): Intent {
		$intent = self::from_order( $order, $onvo_customer_id);
		$intent->set_description( sprintf( 'Orden de Renovación ID: %s', $order->get_id() ) );

		return $intent;
	}

	public static function from_order( \WC_Order $order, string $onvo_customer_id ): Intent {
		$intent = new Intent();
		$intent->set_amount( wc_number_to_onvo( $order->get_total( false ) ) );
		$intent->set_customer_id( $onvo_customer_id );
		$intent->set_description( sprintf( 'Orden ID: %s', $order->get_id() ) );
		if ( Currency::isValid( $order->get_currency() ) ) {
			$intent->set_currency( Currency::from( $order->get_currency() ) );
		}

		$intent_id = get_intent_id_for_order( $order );
		if ( $intent_id ) {
			$intent->set_id( $intent_id );
		}

		return $intent;
	}

	public static function from_processed_payment_order(
		$payment_intent_id,
		$order_id,
		$merchant_key,
		$cart_total
	): Intent {
		$intent = new Intent();
		$intent->set_id( $payment_intent_id );
		$intent->set_amount( wc_number_to_onvo( $cart_total ) );
		$intent->set_description( sprintf( '%s - Orden ID: %s', $merchant_key, $order_id ) );

		return $intent;
	}

	private static function get_customer_id( array $api_intent ): ?string {
		if ( isset( $api_intent['customerId'] ) ) {
			return $api_intent['customerId'];
		} elseif ( isset( $api_intent['customer']['id'] ) ) {
			return $api_intent['customer']['id'];
		}

		return null;
	}

	private static function get_payment_method_id( array $api_intent ): ?string {
		if ( isset( $api_intent['paymentMethodId'] ) ) {
			return $api_intent['paymentMethodId'];
		} elseif ( isset( $api_intent['paymentMethod']['id'] ) ) {
			return $api_intent['paymentMethod']['id'];
		}

		return null;
	}
}

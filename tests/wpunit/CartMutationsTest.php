<?php

class CartMutationsTest extends \Tests\WPGraphQL\WooCommerce\TestCase\WooGraphQLTestCase {
    // tests
    public function testAddToCartMutationWithProduct() {
		$product_id = $this->factory->product->createSimple();

		$query = '
            mutation( $input: AddToCartInput! ) {
                addToCart( input: $input ) {
                    clientMutationId
                    cartItem {
                        key
                        product {
                            node {
                                id
                            }
                        }
                        variation {
							node {
								id
							}
                        }
                        quantity
                        subtotal
                        subtotalTax
                        total
                        tax
                    }
                }
            }
		';

		$variables = array(
			'input' => array(
                'clientMutationId' => 'someId',
                'productId'        => $product_id,
                'quantity'         => 2,
            ),
		);

        $response  = $this->graphql( compact( 'query', 'variables' ) );

        // Confirm valid response
		$this->assertIsValidQueryResponse( $response );

		// Get/validate cart item key.
		$cart_item_key = $this->lodashGet( $response, 'data.addToCart.cartItem.key' );
		$this->assertNotEmpty( $cart_item_key );

		// Get cart item data.
		$cart      = \WC()->cart;
        $cart_item = $cart->get_cart_item( $cart_item_key );
        $this->assertNotEmpty( $cart_item );

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'addToCart.clientMutationId', 'someId' ),
				$this->expectedObject( 'addToCart.cartItem.key', $cart_item_key ),
				$this->expectedObject( 'addToCart.cartItem.product.node.id', $this->toRelayId( 'product', $product_id ) ),
				$this->expectedObject( 'addToCart.cartItem.quantity', 2 ),
				$this->expectedObject( 'addToCart.cartItem.subtotal', wc_graphql_price( $cart_item['line_subtotal'] ) ),
				$this->expectedObject( 'addToCart.cartItem.subtotalTax', wc_graphql_price( $cart_item['line_subtotal_tax'] ) ),
				$this->expectedObject( 'addToCart.cartItem.total', wc_graphql_price( $cart_item['line_total'] ) ),
				$this->expectedObject( 'addToCart.cartItem.tax', wc_graphql_price( $cart_item['line_tax'] ) ),
			)
		);
    }

    public function testAddToCartMutationWithProductVariation() {
		$ids      = $this->factory->product_variation->createSome();

		$query     = '
			mutation( $input: AddToCartInput! ) {
				addToCart( input: $input ) {
					clientMutationId
					cartItem {
						key
						product {
							node {
								id
							}
						}
						variation {
							node {
								id
							}
						}
						quantity
						subtotal
						subtotalTax
						total
						tax
					}
				}
			}
		';

		$variables = array(
			'input' => array(
                'clientMutationId' => 'someId',
                'productId'        => $ids['product'],
                'quantity'         => 3,
				'variationId'      => $ids['variations'][0],
				'variation'        => array(
					array(
						'attributeName'  => 'color',
						'attributeValue' => 'red',
					),
				),
            )
		);

        $response  = $this->graphql( compact( 'query', 'variables' ) );

        // Confirm valid response
		$this->assertIsValidQueryResponse( $response );

		// Get/validate cart item key.
		$cart_item_key = $this->lodashGet( $response, 'data.addToCart.cartItem.key' );
		$this->assertNotEmpty( $cart_item_key );

		// Get cart item data.
		$cart      = \WC()->cart;
        $cart_item = $cart->get_cart_item( $cart_item_key );
        $this->assertNotEmpty( $cart_item );

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'addToCart.clientMutationId', 'someId' ),
				$this->expectedObject( 'addToCart.cartItem.key', $cart_item_key ),
				$this->expectedObject( 'addToCart.cartItem.product.node.id', $this->toRelayId( 'product', $ids['product'] ) ),
				$this->expectedObject( 'addToCart.cartItem.variation.node.id', $this->toRelayId( 'product_variation', $ids['variations'][0] ) ),
				$this->expectedObject( 'addToCart.cartItem.quantity', 3 ),
				$this->expectedObject( 'addToCart.cartItem.subtotal', wc_graphql_price( $cart_item['line_subtotal'] ) ),
				$this->expectedObject( 'addToCart.cartItem.subtotalTax', wc_graphql_price( $cart_item['line_subtotal_tax'] ) ),
				$this->expectedObject( 'addToCart.cartItem.total', wc_graphql_price( $cart_item['line_total'] ) ),
				$this->expectedObject( 'addToCart.cartItem.tax', wc_graphql_price( $cart_item['line_tax'] ) ),
			)
		);
    }

    public function testUpdateCartItemQuantitiesMutation() {
        // Create/add some products to the cart.
        $cart_item_data = array(
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 2,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 5,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 1,
			),
		);

		// Store cart item keys for use in mutation.
		$keys = $this->factory->cart->add( ...$cart_item_data );

        // Define mutation.
        $query = '
            mutation( $input: UpdateItemQuantitiesInput! ) {
                updateItemQuantities( input: $input ) {
                    clientMutationId
                    updated {
                        key
                        quantity
                    }
                    removed {
                        key
                        quantity
                    }
                    items {
                        key
                        quantity
                    }
                }
            }
		';

		// Define variables
		$variables = array(
			'input' => array(
				'clientMutationId' => 'someId',
				'items'            => array(
					array( 'key' => $keys[0], 'quantity' => 4 ),
					array( 'key' => $keys[1], 'quantity' => 2 ),
					array( 'key' => $keys[2], 'quantity' => 0 ),
				),
			),
		);

		// Execute mutation.
        $response = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'updateItemQuantities.clientMutationId', 'someId' ),
				$this->expectedNode( 'updateItemQuantities.updated', array( 'key' => $keys[0], 'quantity' => 4 ) ),
				$this->expectedNode( 'updateItemQuantities.updated', array( 'key' => $keys[1], 'quantity' => 2 ) ),
				$this->expectedNode( 'updateItemQuantities.removed', array( 'key' => $keys[2], 'quantity' => 1 ) ),
				$this->expectedNode( 'updateItemQuantities.items', array( 'key' => $keys[0], 'quantity' => 4 ) ),
				$this->expectedNode( 'updateItemQuantities.items', array( 'key' => $keys[1], 'quantity' => 2 ) ),
				$this->expectedNode( 'updateItemQuantities.items', array( 'key' => $keys[2], 'quantity' => 1 ) ),
			)
		);
    }

    public function testRemoveItemsFromCartMutation() {
		// Create/add some products to the cart.
        $cart_item_data = array(
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 2,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 5,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 1,
			)
		);

		// Store cart item keys for use in mutation.
		$keys = $this->factory->cart->add( ...$cart_item_data );

		$query = '
			mutation( $input: RemoveItemsFromCartInput! ) {
				removeItemsFromCart( input: $input ) {
					clientMutationId
					cartItems {
						key
					}
				}
			}
		';

		// Define expected response data.
		$expected = array( $this->expectedObject( 'removeItemsFromCart.clientMutationId', 'someId' ) );
		foreach( $keys as $key ) {
			$expected[] = $this->expectedNode( 'removeItemsFromCart.cartItems', compact( 'key' ) );
		}

		$variables = array(
			'input' => array(
                'clientMutationId' => 'someId',
                'keys'             => $keys,
            )
		);

		// Execute mutation w/ "keys" array.
        $response = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertQuerySuccessful( $response, $expected );

		// Confirm none of the items in cart.
		foreach( $keys as $key ) {
			$this->assertEmpty(
				\WC()->cart->get_cart_item( $key ),
				"{$key} still in cart after \"removeItemsFromCart\" mutation."
			);
		}

		// Add more items and execute mutation with "all" flag.
		$keys = $this->factory->cart->add( ...$cart_item_data );

		$variables = array(
			'input' => array(
                'clientMutationId' => 'someId',
                'all'              => true
            ),
		);

		$response = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertQuerySuccessful( $response, $expected );

		// Confirm none of the items in cart.
		foreach( $keys as $key ) {
			$this->assertEmpty(
				\WC()->cart->get_cart_item( $key ),
				"{$key} still in cart after \"removeItemsFromCart\" mutation with \"all\" flag."
			);
		}
    }

    public function testRestoreCartItemsMutation() {
        // Create/add some products to the cart.
		$cart_item_data = array(
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 2,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 5,
			),
			array(
				'product_id' => $this->factory->product->createSimple(),
				'quantity'  => 1,
			)
		);
		$keys = $this->factory->cart->add( ...$cart_item_data );
		$this->factory->cart->remove( ...$keys );

		$query = '
            mutation( $input: RestoreCartItemsInput! ) {
                restoreCartItems( input: $input ) {
                    clientMutationId
                    cartItems {
                        key
                    }
                }
            }
		';

		$variables = array(
			'input' => array(
                'clientMutationId' => 'someId',
                'keys'             => $keys,
            )
		);

        $response = $this->graphql( compact( 'query', 'variables' ) );

		$expected = array( $this->expectedObject( 'restoreCartItems.clientMutationId', 'someId' ) );
		foreach( $keys as $key ) {
			$expected[] = $this->expectedNode( 'restoreCartItems.cartItems', compact( 'key' ) );
		}

		$this->assertQuerySuccessful( $response, $expected );

		// Confirm items in cart.
		foreach( $keys as $key ) {
			$this->assertNotEmpty(
				\WC()->cart->get_cart_item( $key ),
				"{$key} not found in cart after \"restoreCartItems\" mutation."
			);
		}
    }

    public function testEmptyCartMutation() {
        // Create/add some products to the cart.
		$product_id    = $this->factory->product->createSimple();
        $cart          = \WC()->cart;
        $cart_item_key = $cart->add_to_cart( $product_id, 1 );
		$cart_item     = $cart->get_cart_item( $cart_item_key );

        $query = '
            mutation( $input: EmptyCartInput! ) {
                emptyCart( input: $input ) {
                    clientMutationId
                    deletedCart {
                        contents {
                            nodes {
                                key
                                product {
                                    node {
                                        id
                                    }
                                }
                                variation {
									node {
										id
									}
                                }
                                quantity
                                subtotal
                                subtotalTax
                                total
                                tax
                            }
                        }
                    }
                }
            }
        ';

        $variables = array(
            'input' => array( 'clientMutationId' => 'someId' ),
        );
        $response  = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'emptyCart.clientMutationId', 'someId' ),
				$this->expectedNode(
					'emptyCart.deletedCart.contents.nodes',
					array(
						'key'         => $cart_item['key'],
						'product'     => array(
							'node' => array(
								'id' => $this->toRelayId( 'product', $cart_item['product_id'] ),
							),
						),
						'variation'   => null,
						'quantity'    => $cart_item['quantity'],
						'subtotal'    => wc_graphql_price( $cart_item['line_subtotal'] ),
						'subtotalTax' => wc_graphql_price( $cart_item['line_subtotal_tax'] ),
						'total'       => wc_graphql_price( $cart_item['line_total'] ),
						'tax'         => wc_graphql_price( $cart_item['line_tax'] ),
					)
				),
			)
		);

        $this->assertTrue( \WC()->cart->is_empty() );
    }

    public function testApplyCouponMutation() {
        // Create products.
        $product_id = $this->factory->product->createSimple(
            array( 'regular_price' => 100 )
        );

        // Create coupon.
        $coupon_code = wc_get_coupon_code_by_id(
			$this->factory->coupon->create(
                array(
                    'amount'      => 0.5,
                    'product_ids' => array( $product_id )
                )
            )
        );

        // Add items to carts.
        $cart          = \WC()->cart;
        $cart_item_key = $cart->add_to_cart( $product_id, 1 );

        $old_total = \WC()->cart->get_cart_contents_total();

        $query = '
            mutation( $input: ApplyCouponInput! ) {
                applyCoupon( input: $input ) {
                    clientMutationId
                    cart {
                        appliedCoupons {
							code
                        }
                        contents {
                            nodes {
                                key
                                product {
                                    node {
                                        id
                                    }
                                }
                                quantity
                                subtotal
                                subtotalTax
                                total
                                tax
                            }
                        }
                    }
                }
            }
        ';

        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'code'             => $coupon_code,
            ),
        );
        $response  = $this->graphql( compact( 'query', 'variables' ) );

        // Get updated cart item.
        $cart_item = $cart->get_cart_item( $cart_item_key );

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'applyCoupon.clientMutationId', 'someId' ),
				$this->expectedNode(
					'applyCoupon.cart.appliedCoupons',
					array(
						'code' => $coupon_code,
					)
				),
				$this->expectedNode(
					'applyCoupon.cart.contents.nodes',
					array(
						'key'          => $cart_item['key'],
						'product'      => array(
							'node' => array(
								'id' => $this->toRelayId( 'product', $cart_item['product_id'] ),
							),
						),
						'quantity'     => $cart_item['quantity'],
						'subtotal'     => wc_graphql_price( $cart_item['line_subtotal'] ),
						'subtotalTax'  => wc_graphql_price( $cart_item['line_subtotal_tax'] ),
						'total'        => wc_graphql_price( $cart_item['line_total'] ),
						'tax'          => wc_graphql_price( $cart_item['line_tax'] ),
					)
				),
			)
		);

        $new_total = \WC()->cart->get_cart_contents_total();

        // Use --debug to view.
        codecept_debug( array( 'old' => $old_total, 'new' => $new_total ) );

        $this->assertTrue( $old_total > $new_total );
    }

    public function testApplyCouponMutationWithInvalidCoupons() {
        $cart = WC()->cart;

        // Create products.
        $product_id = $this->factory->product->createSimple();

        // Create invalid coupon codes.
        $coupon_id = $this->factory->coupon->create(
            array( 'product_ids' => array( $product_id ) )
        );
        $expired_coupon_code = wc_get_coupon_code_by_id(
            $this->factory->coupon->create(
                array(
                    'product_ids'  => array( $product_id ),
                    'date_expires' => time() - 20,
                )
            )
        );
        $applied_coupon_code = wc_get_coupon_code_by_id(
            $this->factory->coupon->create(
                array( 'product_ids' => array( $product_id ) )
            )
        );

        // Add items to carts.
        $cart_item_key = $cart->add_to_cart( $product_id, 1 );
        $cart->apply_coupon( $applied_coupon_code );

        $old_total = \WC()->cart->get_cart_contents_total();

        $query = '
            mutation( $input: ApplyCouponInput! ) {
                applyCoupon( input: $input ) {
                    clientMutationId
                }
            }
        ';

        /**
         * Assertion One
         *
         * Can't pass coupon ID as coupon 'code'. Mutation should fail.
         */
        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'code'             => '$coupon_id',
            ),
        );
        $response  = $this->graphql( compact( 'query', 'variables' ) );

        $this->assertNotEmpty( $response['errors'] );
        $this->assertEmpty( $response['data']['applyCoupon'] );

        /**
         * Assertion Two
         *
         * Can't pass expired coupon code. Mutation should fail.
         */
        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'code'             => $expired_coupon_code,
            ),
        );
        $response  = $this->graphql( compact( 'query', 'variables' ) );

        $this->assertNotEmpty( $response['errors'] );
        $this->assertEmpty( $response['data']['applyCoupon'] );

        /**
         * Assertion Three
         *
         * Can't pass coupon already applied to the cart. Mutation should fail.
         */
        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'code'             => $applied_coupon_code,
            ),
        );
        $response  = $this->graphql( compact( 'query', 'variables' ) );

        $this->assertNotEmpty( $response['errors'] );
        $this->assertEmpty( $response['data']['applyCoupon'] );

        $this->assertEquals( $old_total, \WC()->cart->get_cart_contents_total() );
    }

    public function testRemoveCouponMutation() {
        // Create product and coupon.
        $product_id  = $this->factory->product->createSimple();
        $coupon_code = wc_get_coupon_code_by_id(
            $this->factory->coupon->create(
                array( 'product_ids' => array( $product_id ) )
            )
        );

		// Add item and coupon to cart and get total..
        $cart          = \WC()->cart;
        $cart_item_key = $cart->add_to_cart( $product_id, 3 );
        $cart->apply_coupon( $coupon_code );

        $query = '
            mutation removeCoupons( $input: RemoveCouponsInput! ) {
                removeCoupons( input: $input ) {
                    clientMutationId
                    cart {
                        appliedCoupons {
							code
                        }
                        contents {
                            nodes {
                                key
                                product {
                                    node {
                                        id
                                    }
                                }
                                quantity
                                subtotal
                                subtotalTax
                                total
                                tax
                            }
                        }
                    }
                }
            }
        ';

        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'codes'            => array( $coupon_code ),
            ),
        );
        $response  = $this->graphql( compact( 'query', 'variables' ) );

        // Get updated cart item.
		$cart_item = \WC()->cart->get_cart_item( $cart_item_key );

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'removeCoupons.clientMutationId', 'someId' ),
				$this->expectedObject( 'removeCoupons.cart.appliedCoupons', 'null' ),
				$this->expectedNode(
					'removeCoupons.cart.contents.nodes',
					array(
						'key'          => $cart_item['key'],
						'product'      => array(
							'node' => array(
								'id' => $this->toRelayId( 'product', $cart_item['product_id'] ),
							)
						),
						'quantity'     => $cart_item['quantity'],
						'subtotal'     => wc_graphql_price( $cart_item['line_subtotal'] ),
						'subtotalTax'  => wc_graphql_price( $cart_item['line_subtotal_tax'] ),
						'total'        => wc_graphql_price( $cart_item['line_total'] ),
						'tax'          => wc_graphql_price( $cart_item['line_tax'] ),
					)
				),
			)
		);
    }

    public function testAddFeeMutation() {
        // Create product and coupon.
        $product_id  = $this->factory->product->createSimple();
        $coupon_code = wc_get_coupon_code_by_id(
            $this->factory->coupon->create(
                array( 'product_ids' => array( $product_id ) )
            )
        );

        // Add item and coupon to cart.
        $cart = \WC()->cart;
        $cart->add_to_cart( $product_id, 3 );
        $cart->apply_coupon( $coupon_code );

        $query = '
            mutation( $input: AddFeeInput! ) {
                addFee( input: $input ) {
                    clientMutationId
                    cartFee {
                        id
                        name
                        taxClass
                        taxable
                        amount
                        total
                    }
                }
            }
        ';

        $variables = array(
            'input' => array(
                'clientMutationId' => 'someId',
                'name'             => 'extra_fee',
                'amount'           => 49.99,
            ),
        );
        $response  = $this->graphql( compact( 'query', 'variables' ) );
        $this->assertArrayHasKey( 'errors', $response );

        wp_set_current_user( $this->shop_manager );
        $response  = $this->graphql( compact( 'query', 'variables' ) );

		$cart = WC()->cart;
		$fee  = ( $cart->get_fees() )['extra_fee'];

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'addFee.clientMutationId', 'someId' ),
				$this->expectedObject(
					'addFee.cartFee',
					array(
						'id'       => $fee->id,
						'name'     => $fee->name,
						'taxClass' => ! empty( $fee->tax_class ) ? $fee->tax_class : null,
						'taxable'  => $fee->taxable,
						'amount'   => (float) $fee->amount,
						'total'    => (float) $fee->total,
					)
				),
			)
		);
	}

	public function testAddToCartMutationErrors() {
		// Create products.
        $product_id    = $this->factory->product->createSimple(
			array(
				'manage_stock'   => true,
				'stock_quantity' => 1,
			)
		);
		$variation_ids = $this->factory->product_variation->createSome();

		$product   = \wc_get_product( $variation_ids['product'] );
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( 'test' );
		$attribute->set_options( array( 'yes', 'no' ) );
		$attribute->set_position( 3 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$attributes = array_values( $product->get_attributes() );
		$attributes[] = $attribute;
		$product->set_attributes( $attributes );
		$product->save();

		$query     = '
			mutation( $input: AddToCartInput! ) {
				addToCart( input: $input ) {
					clientMutationId
					cartItem {
						key
						product {
							node {
								id
							}
						}
						variation {
							node {
								id
							}
						}
						quantity
						subtotal
						subtotalTax
						total
						tax
					}
				}
			}
		';

		\WC()->session->set( 'wc_notices', null );
		$variables = array(
			'input' => array(
				'clientMutationId' => 'someId',
				'productId'        => $variation_ids['product'],
				'quantity'         => 5,
				'variationId'      => $variation_ids['variations'][0],
			),
		);
		$missing_attributes = $this->graphql( compact( 'query', 'variables'  ) );
		$this->assertArrayHasKey( 'errors', $missing_attributes );

		\WC()->session->set( 'wc_notices', null );
		$variables = array(
			'input' => array(
                'clientMutationId' => 'someId',
                'productId'        => $product_id,
                'quantity'         => 5,
            ),
		);
		$not_enough_stock = $this->graphql( compact( 'query', 'variables'  ) );
		$this->assertArrayHasKey( 'errors', $not_enough_stock );
	}

	public function testAddCartItemsMutationAndErrors() {
		// Create variable product for later use.
		$variation_ids = $this->factory->product_variation->createSome();
		$product       = \wc_get_product( $variation_ids['product'] );
		$attribute     = new WC_Product_Attribute();
		$attribute->set_id( 0 );
		$attribute->set_name( 'test' );
		$attribute->set_options( array( 'yes', 'no' ) );
		$attribute->set_position( 3 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$attributes = array_values( $product->get_attributes() );
		$attributes[] = $attribute;
		$product->set_attributes( $attributes );
		$product->save();

		$product_one     = $this->factory->product->createSimple();
		$invalid_product = 1000;

		$query = '
			mutation ($input: AddCartItemsInput!) {
				addCartItems(input: $input) {
					clientMutationId
					added {
						product {
							node { databaseId }
						}
						variation {
							node { databaseId }
						}
						quantity
					}
					cartErrors {
						type
						reasons
						productId
						quantity
						variationId
						variation {
							attributeName
							attributeValue
						}
						extraData
					}
				}
			}
		';

		$variables = array(
			'input' => array(
				'clientMutationId' => 'someId',
				'items'            => array(
					array(
						'productId' => $product_one,
						'quantity'  => 2,
					),
					array(
						'productId'   => $variation_ids['product'],
						'quantity'    => 5,
						'variationId' => $variation_ids['variations'][0],
					),
					array(
						'productId' => $invalid_product,
						'quantity'  => 4
					),
					array(
						'productId'   => $variation_ids['product'],
						'quantity'    => 3,
						'variationId' => $variation_ids['variations'][1],
						'variation'   => array(
							array(
								'attributeName'  => 'test',
								'attributeValue' => 'yes',
							),
							array(
								'attributeName'  => 'color',
								'attributeValue' => 'green',
							),
						)
					)
				),
			),
		);

		$response = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'addCartItems.clientMutationId', 'someId' ),
				$this->expectedNode(
					'addCartItems.added',
					array(
						'product'   => array(
							'node' => array( 'databaseId' => $product_one )
						),
						'variation' => null,
						'quantity'  => 2,
					)
				),
				$this->expectedNode(
					'addCartItems.added',
					array(
						'product'   => array(
							'node' => array( 'databaseId' => $variation_ids['product'] )
						),
						'variation' => array(
							'node' => array( 'databaseId' => $variation_ids['variations'][1] )
						),
						'quantity'  => 3,
					)
				),
				$this->expectedNode(
					'addCartItems.cartErrors',
					array(
						'type'        => 'INVALID_CART_ITEM',
						'reasons'     => array( 'color and test are required fields' ),
						'productId'   => $variation_ids['product'],
						'quantity'    => 5,
						'variationId' => $variation_ids['variations'][0],
						'variation'   => null,
						'extraData'   => null
					)
				),
				$this->expectedNode(
					'addCartItems.cartErrors',
					array(
						'type'        => 'INVALID_CART_ITEM',
						'reasons'     => array( 'No product found matching the ID provided' ),
						'productId'   => $invalid_product,
						'quantity'    => 4,
						'variationId' => null,
						'variation'   => null,
						'extraData'   => null
					)
				),
			)
		);
	}

	public function testFillCartMutationAndErrors() {
		// Create products.
        $product_one = $this->factory->product->createSimple( array( 'regular_price' => 100 ) );
		$product_two = $this->factory->product->createSimple( array( 'regular_price' => 40 ) );

        // Create coupons.
        $coupon_code_one = wc_get_coupon_code_by_id(
			$this->factory->coupon->create(
				array(
					'amount'      => 0.5,
					'product_ids' => array( $product_one )
				)
			)
		);
		$coupon_code_two = wc_get_coupon_code_by_id(
            $this->factory->coupon->create(
                array(
                    'amount'      => 0.2,
                    'product_ids' => array( $product_two )
                )
            )
        );

		$invalid_product         = 1000;
		$invalid_coupon          = 'failed';
		$invalid_shipping_method = 'fakityfake-shipping';

		$this->factory->shipping_zone->createLegacyFlatRate();

		$query = '
			mutation ($input: FillCartInput!) {
				fillCart( input: $input ) {
					clientMutationId
					cart {
						chosenShippingMethods
						contents {
							nodes {
								product {
									node { databaseId }
								}
								quantity
								variation {
									node { databaseId }
								}
							}
						}
						appliedCoupons {
							code
							discountAmount
							discountTax
						}
					}
					cartErrors {
						type
						... on CartItemError {
							reasons
							productId
							quantity
						}
						... on CouponError {
							reasons
							code
						}
						... on ShippingMethodError {
							chosenMethod
							package
						}
					}
				}
			}
		';

		$variables = array(
			'input' => array(
				'clientMutationId' => 'someId',
				'items'            => array(
					array(
						'productId' => $product_one,
						'quantity'  => 3,
					),
					array(
						'productId' => $product_two,
						'quantity'  => 2,
					),
					array(
						'productId' => $invalid_product,
						'quantity'  => 4,
					),
				),
				'coupons'           => array( $coupon_code_one, $coupon_code_two, $invalid_coupon ),
				'shippingMethods'   => array( 'legacy_flat_rate', $invalid_shipping_method ),
			),
		);

		$response = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertQuerySuccessful(
			$response,
			array(
				$this->expectedObject( 'fillCart.clientMutationId', 'someId' ),
				$this->expectedNode( 'fillCart.cart.chosenShippingMethods', 'legacy_flat_rate' ),
				$this->expectedNode(
					'fillCart.cart.contents.nodes',
					array(
						'product'   => array(
							'node' => array( 'databaseId' => $product_one ),
						),
						'quantity'  => 3,
						'variation' => null,
					)
				),
				$this->expectedNode(
					'fillCart.cart.contents.nodes',
					array(
						'product'   => array(
							'node' => array( 'databaseId' => $product_two ),
						),
						'quantity'  => 2,
						'variation' => null,
					)
				),
				$this->expectedNode(
					'fillCart.cart.appliedCoupons',
					array(
						'code'           => $coupon_code_one,
						'discountAmount' => \wc_graphql_price(
							\WC()->cart->get_coupon_discount_amount( $coupon_code_one, true )
						),
						'discountTax' => \wc_graphql_price(
							\WC()->cart->get_coupon_discount_tax_amount( $coupon_code_one )
						),
					)
				),
				$this->expectedNode(
					'fillCart.cart.appliedCoupons',
					array(
						'code' => $coupon_code_two,
						'discountAmount' => \wc_graphql_price(
							\WC()->cart->get_coupon_discount_amount( $coupon_code_two, true )
						),
						'discountTax' => \wc_graphql_price(
							\WC()->cart->get_coupon_discount_tax_amount( $coupon_code_two )
						),
					)
				),
				$this->expectedNode(
					'fillCart.cartErrors',
					array(
						'type'        => 'INVALID_CART_ITEM',
						'reasons'     => array( 'No product found matching the ID provided' ),
						'productId'   => $invalid_product,
						'quantity'    => 4
					)
				),
				$this->expectedNode(
					'fillCart.cartErrors',
					array(
						'type'        => 'INVALID_COUPON',
						'reasons'     => array( "Coupon \"{$invalid_coupon}\" does not exist!" ),
						'code'        => $invalid_coupon,
					)
				),
				$this->expectedNode(
					'fillCart.cartErrors',
					array(
						'type'         => 'INVALID_SHIPPING_METHOD',
						'chosenMethod' => $invalid_shipping_method,
						'package'      => 1,
					)
				),
			)
		);
	}
}

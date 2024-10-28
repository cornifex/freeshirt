<?php

namespace Drupal\mystore_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Enables free shirt giveaways on checkout.
 *
 * @CommerceCheckoutPane(
 *   id = "mystore_commerce_free_shirt",
 *   label = @Translation("Free Shirt"),
 *   wrapper_element = "fieldset",
 * )
 */
class FreeShirt extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {

    if (!$this->orderHasTriggeringProducts()) {
      return [];
    }

    $shirt_options = $this->configuration['available_options'] ?? [];

    if ($shirt_options) {
      $shirt_options = array_filter($shirt_options);
    }

    $pane_form['disclaimer'] = [
      '#markup' => $this->configuration['disclaimer'] ?? '',
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $pane_form['shirt'] = [
      '#title' => 'Select your size',
      '#type' => 'select',
      '#options' => [0 => 'None'] + $shirt_options,
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    parent::submitPaneForm($pane_form, $form_state, $complete_form);
    $values = $form_state->getValue($pane_form['#parents']);
    $selection = $values['shirt'];
    // Create a line item if a user has selected a shirt. Fulfillment is handled based on this.
    if ($selection) {
      $order_item_values = [
        'type' => 'free_shirt',
        'title' => $this->t('Free Shirt: ' . $selection),
        'quantity' => 1,
        'unit_price' => new Price(0, 'USD')
      ];
      $order_item = $this->entityTypeManager->getStorage('commerce_order_item')->create($order_item_values);
      $order_item->save();
      $this->order->addItem($order_item);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $triggering_products = $this->configuration['triggering_products'] ?? [];

    // Load product entities for triggering product field defaults.
    if ($triggering_products) {
      $triggering_products = $this->entityTypeManager->getStorage('commerce_product')->loadMultiple($triggering_products);
    }

    $form['disclaimer'] = [
      '#title' => 'Disclaimer',
      '#type' => 'textarea',
      '#default_value' => $this->configuration['disclaimer'] ?? '',
      '#required' => TRUE,
    ];

    $form['triggering_products'] = [
      '#title' => $this->t('Triggering Products'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'commerce_product',
      '#tags' => TRUE,
      '#default_value' => $triggering_products,
      '#required' => TRUE,
    ];

    $form['available_options'] = [
      '#title' => $this->t('Available Options'),
      '#type' => 'checkboxes',
      '#options' => [
        'Men - S' => $this->t('Men - S'),
        'Men - M' => $this->t('Men - M'),
        'Men - L' => $this->t('Men - L'),
        'Men - XL' => $this->t('Men - XL'),
        'Men - XXL' => $this->t('Men - XXL'),
        'Women - XS' => $this->t('Women - XS'),
        'Women - S' => $this->t('Women - S'),
        'Women - M' => $this->t('Women - M'),
        'Women - L' => $this->t('Women - L'),
        'Women - XL' => $this->t('Women - XL'),
      ],
      '#default_value' => $this->configuration['available_options'] ?? [],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    $this->configuration['disclaimer'] = $values['disclaimer'];
    $this->configuration['available_options'] = $values['available_options'];
    $this->configuration['triggering_products'] = array_map(static function ($item) {
      return $item['target_id'];
    }, $values['triggering_products']);
  }

  /**
   * Check to see if the order contains triggering products.
   *
   * @return bool
   */
  private function orderHasTriggeringProducts(): bool {
    foreach ($this->order->getItems() as $orderItem) {
      if (($purchasableEntity = $orderItem->getPurchasedEntity()) && $purchasableEntity->hasField('product_id')) {
        $product_id = $purchasableEntity->get('product_id')->target_id;
        if (in_array($product_id, $this->configuration['triggering_products'], TRUE)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}

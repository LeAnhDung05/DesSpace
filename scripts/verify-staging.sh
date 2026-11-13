#!/usr/bin/env bash
set -euo pipefail

dc() {
  docker compose --profile tools run --rm wpcli "$@"
}

echo "[1/5] WordPress installation"
dc core is-installed

echo "[2/5] Required plugins and theme"
dc plugin is-active woocommerce
dc plugin is-active despace-core
dc theme is-active despace-child

echo "[3/5] WooCommerce and DeSpace runtime hooks"
dc eval '
$checks = array(
  "woocommerce" => class_exists("WooCommerce") && function_exists("WC"),
  "cart_ajax_add" => false !== has_action("wp_ajax_nopriv_despace_cart_add"),
  "checkout_module" => class_exists("DeSpace\\CheckoutOrders\\CheckoutOrdersModule"),
  "inventory_observer" => false !== has_action("woocommerce_product_set_stock", array("DeSpace\\Inventory\\Events\\InventoryStockObserver", "on_set_stock")),
  "order_tracking_shortcode" => shortcode_exists("despace_order_tracking"),
  "cart_page" => wc_get_page_id("cart") > 0,
  "checkout_page" => wc_get_page_id("checkout") > 0,
  "account_page" => wc_get_page_id("myaccount") > 0,
);
$failed = array();
foreach ($checks as $name => $passed) {
  echo ($passed ? "PASS " : "FAIL ") . $name . PHP_EOL;
  if (!$passed) $failed[] = $name;
}
if ($failed) {
  fwrite(STDERR, "Failed checks: " . implode(", ", $failed) . PHP_EOL);
  exit(1);
}
'

echo "[4/5] Demo catalog"
dc eval '
$products = wc_get_products(array("limit" => 1, "status" => "publish"));
if (!$products) {
  fwrite(STDERR, "No published demo products found. Run the seed script first." . PHP_EOL);
  exit(1);
}
echo "PASS published WooCommerce products exist" . PHP_EOL;
'

echo "[5/5] Staging smoke verification complete"

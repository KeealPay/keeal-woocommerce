/**
 * WooCommerce Blocks: Keeal redirect gateway.
 * Uses wcSettings.getPaymentMethodData (same as core wc-payment-method-*.js).
 */
(function () {
  var register =
    window.wc &&
    window.wc.wcBlocksRegistry &&
    window.wc.wcBlocksRegistry.registerPaymentMethod;
  if (typeof register !== "function") {
    return;
  }

  var el = window.wp && window.wp.element && window.wp.element.createElement;
  var getPaymentMethodData =
    window.wc && window.wc.wcSettings && window.wc.wcSettings.getPaymentMethodData;

  var name = "keeal_hosted_checkout";
  var settings =
    (getPaymentMethodData && getPaymentMethodData(name, {})) || {};

  var labelText = settings.title || "Keeal Payment";
  var desc = settings.description || "";
  var iconSrc = settings.iconSrc || "";
  var features = Array.isArray(settings.supports) ? settings.supports : ["products"];

  var labelNode =
    el && iconSrc
      ? el(
          "span",
          {
            className: "keeal-wc-blocks-label",
            style: { display: "inline-flex", alignItems: "center", gap: "0.5rem" },
          },
          el("img", {
            src: iconSrc,
            alt: "",
            width: 28,
            height: 28,
            style: { display: "block", borderRadius: "6px", objectFit: "contain" },
          }),
          el("span", {}, labelText)
        )
      : el
        ? el("span", {}, labelText)
        : labelText;

  register({
    name: name,
    label: labelNode,
    content: el ? el("div", { className: "keeal-wc-blocks-desc" }, desc) : desc,
    edit: el ? el("div", { className: "keeal-wc-blocks-desc" }, desc) : desc,
    canMakePayment: function () {
      return true;
    },
    ariaLabel: labelText,
    supports: { features: features },
  });
})();

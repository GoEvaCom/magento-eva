define([
  "ko",
  "uiComponent",
  "Magento_Checkout/js/model/quote",
  "mage/storage",
  "Magento_Checkout/js/model/url-builder",
  "Magento_Customer/js/model/customer",
], function (ko, Component, quote, storage, urlBuilder, customer) {
  "use strict";

  return Component.extend({
    defaults: {
      template: "GoEvaCom_Integration/checkout/shipping/delivery-notes",
    },

    initialize: function () {
      console.log(window);
      this._super();
      this.deliveryInstructions = ko.observable("");
      this.isVisible = ko.computed(function () {
        var shippingMethod = quote.shippingMethod();
        return shippingMethod && shippingMethod.method_code === "evadelivery";
      });

      this.deliveryInstructions.subscribe(
        function (value) {
          this.saveDeliveryInstructions(value);
        }.bind(this)
      );

      return this;
    },

    getCode: function () {
      return "delivery-instructions";
    },

    saveDeliveryInstructions: function (instructions) {
      var serviceUrl = urlBuilder.createUrl(
        "/carts/mine/delivery-instructions",
        {}
      );
      var payload = {
        deliveryInstructions: instructions,
      };

      if (!customer.isLoggedIn()) {
        serviceUrl = urlBuilder.createUrl(
          "/guest-carts/:cartId/delivery-instructions",
          {
            cartId: quote.getQuoteId(),
          }
        );
      }

      return storage.post(serviceUrl, JSON.stringify(payload));
    },
  });
});

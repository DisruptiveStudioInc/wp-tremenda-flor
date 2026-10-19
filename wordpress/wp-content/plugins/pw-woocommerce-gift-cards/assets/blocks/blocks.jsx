/* global wc, wp */
(() => {
  // Bail if Blocks globals aren’t present
  if (!window.wp || !window.wc) return;

  const { __, sprintf } = wp.i18n || { __: (s) => s, sprintf: (s) => s };
  const { registerPlugin } = wp.plugins;
  const { Notice } = wp.components;
  const { useState } = wp.element;
  const { useDispatch, useSelect } = wp.data;

  // Woo Blocks slot and helpers
  const blocksCheckout = wc.blocksCheckout || wc.blocks || {};
  const { ExperimentalOrderMeta, extensionCartUpdate } = blocksCheckout || {};

  // Cart data store key
  const cartStore = (wc.wcBlocksData && wc.wcBlocksData.cartStore) || 'wc/store/cart';

  if (!ExperimentalOrderMeta || !useDispatch || !useSelect) return;

  // Store API extensions namespace
  const NAMESPACE = 'pw-gift-cards';

  const ApplyGiftCard = () => {
    const [code, setCode] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [notice, setNotice] = useState(null);

    // Get full cart data so we can read our extension data
    const cartData = useSelect(
      (select) => {
        const s = select(cartStore);
        return s && s.getCartData ? s.getCartData() : null;
      },
      []
    );

    // Applied gift cards coming from the Store API extensions payload
    const giftCards =
      (cartData &&
        cartData.extensions &&
        cartData.extensions[NAMESPACE] &&
        cartData.extensions[NAMESPACE].gift_cards) ||
      [];

    const submit = async () => {
      if (!code) {
        setNotice({ status: 'error', message: __('Enter a gift card code.', 'pw-woocommerce-gift-cards') });
        return;
      }
      setIsSubmitting(true);
      setNotice(null);

      try {
        await extensionCartUpdate({ namespace: NAMESPACE, data: { code } });
        setCode('');
      } catch (err) {
        setNotice({ status: 'error', message: err?.message || __('Something went wrong.', 'pw-woocommerce-gift-cards') });
      } finally {
        setIsSubmitting(false);
      }
    };

    const handleRemove = async (cardNumber) => {
      setIsSubmitting(true);
      setNotice(null);

      try {
        await extensionCartUpdate({
          namespace: NAMESPACE,
          data: {
            code: cardNumber,
            remove: true,
          },
        });
      } catch (err) {
        setNotice({
          status: 'error',
          message: err?.message || __('Unable to remove gift card.', 'pw-woocommerce-gift-cards'),
        });
      } finally {
        setIsSubmitting(false);
      }
    };

    return (
      <ExperimentalOrderMeta>
        <div id="pwgc-redeem-gift-card-container">
          {giftCards.length > 0 && (
            <div className="pwgc-applied-gift-cards">
              <div className="pwgc-applied-gift-cards-title">
                {__('Gift cards applied', 'pw-woocommerce-gift-cards')}
              </div>
              <ul className="pwgc-applied-gift-cards-list">
                {giftCards.map((card) => (
                  <li key={card.number} className="pwgc-applied-gift-cards-item">
                    <div className="pwgc-applied-gift-cards-left">
                      <div className="pwgc-gift-card-number">
                        {card.number}
                      </div>
                      <div className="pwgc-gift-card-remaining-balance">
                        {card.balance && (
                          <div className="pwgc-gift-card-balance">
                            {sprintf(
                              __('Remaining balance is %s', 'pw-woocommerce-gift-cards'),
                              card.balance
                            )}
                          </div>
                        )}
                      </div>
                    </div>

                    <div className="pwgc-applied-gift-cards-right">
                      {card.amount && (
                        <span className="pwgc-gift-card-amount">
                          {card.amount}

                          <button
                            type="button"
                            className="pwgc-remove-gift-card"
                            onClick={() => handleRemove(card.number)}
                            aria-label={sprintf(
                              __('Remove gift card %s', 'pw-woocommerce-gift-cards'),
                              card.number
                            )}
                            disabled={isSubmitting}
                          >
                            ×
                          </button>
                        </span>
                      )}
                    </div>
                  </li>
                ))}
              </ul>
            </div>
          )}

          {!window.pwgcBlocks.hideRedeemForm && (
            <form
              id="pwgc-redeem-gift-card-form"
              onSubmit={(e) => {
                e.preventDefault();
                submit();
              }}
              noValidate
            >
              <label htmlFor="pwgc-redeem-gift-card-number">
                {__('Have a gift card?', 'pw-woocommerce-gift-cards')}
              </label>

              <input
                id="pwgc-redeem-gift-card-number"
                type="text"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                placeholder={__('Gift card number', 'pw-woocommerce-gift-cards')}
                aria-label={__('Gift card number', 'pw-woocommerce-gift-cards')}
                disabled={isSubmitting}
              />

              <button
                type="submit"
                className={`pwgc-apply-gift-card-button wc-block-components-button ${
                  isSubmitting ? 'is-loading' : ''
                }`}
                disabled={isSubmitting}
                aria-busy={isSubmitting}
              >
                {__('Apply Gift Card', 'pw-woocommerce-gift-cards')}
              </button>

              {notice && (
                <Notice status={notice.status} isDismissible={false}>
                  {notice.message}
                </Notice>
              )}
            </form>
           )}
        </div>
      </ExperimentalOrderMeta>
    );
  };

  // Register for both checkout and cart scopes
  registerPlugin('pw-apply-gift-card-checkout', {
    render: ApplyGiftCard,
    scope: 'woocommerce-checkout',
  });
  registerPlugin('pw-apply-gift-card-cart', {
    render: ApplyGiftCard,
    scope: 'woocommerce-cart',
  });
})();
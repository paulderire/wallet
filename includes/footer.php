      </main>
    </div>

    <script>
    // Confirm on destructive actions
    document.addEventListener('click', function(e){
      if (e.target.matches('[data-confirm]')){
        if (!confirm(e.target.getAttribute('data-confirm'))) e.preventDefault();
      }
    });

    // Currency formatting helper - Show RWF amounts with USD equivalent below
    (function(){
      const RWF_TO_USD = 1 / 1300; // Exchange rate: 1,300 RWF = 1 USD
      
      var amounts = document.querySelectorAll('.amount[data-currency][data-amount]');
      amounts.forEach(function(el){
        var currency = el.getAttribute('data-currency') || 'RWF';
        var amount = parseFloat(el.getAttribute('data-amount') || '0');
        
        // Keep original amount (already in RWF), just add USD conversion
        var rwfAmount = amount;
        var usdAmount = rwfAmount * RWF_TO_USD;
        
        // Create dual-currency display
        var primaryText = 'RWF ' + Math.round(rwfAmount).toLocaleString('en-US');
        var secondaryText = '~$' + usdAmount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        
        el.innerHTML = `<span style="display:block;line-height:1.4">
                          <span style="font-weight:inherit">${primaryText}</span>
                          <span style="display:block;font-size:0.75em;opacity:0.7;margin-top:2px">${secondaryText}</span>
                        </span>`;
        
        // Store both values as data attributes
        el.setAttribute('data-amount-rwf', rwfAmount);
        el.setAttribute('data-amount-usd', usdAmount);
      });
    })();
    </script>

    <!-- Modal Container (if used by your pages) -->
    <div id="modal-container"></div>

    <script>
    // Simple Modal System
    window.WCModal = {
      open: function(html) {
        const container = document.getElementById('modal-container');
        if (!container) return;
        
        const overlay = document.createElement('div');
        overlay.id = 'modal-overlay';
        overlay.style.cssText = `
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          backdrop-filter: blur(4px);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 99999;
          padding: 20px;
          animation: fadeIn 0.2s ease;
        `;
        
        const modal = document.createElement('div');
        modal.style.cssText = `
          background: var(--card-bg-solid);
          border: 1.5px solid rgba(var(--card-text-rgb), 0.1);
          border-radius: 16px;
          max-width: 600px;
          width: 100%;
          max-height: 90vh;
          overflow-y: auto;
          box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
          animation: slideUp 0.3s ease;
          position: relative;
        `;
        
        modal.innerHTML = html;
        overlay.appendChild(modal);
        container.appendChild(overlay);
        
        // Close on overlay click
        overlay.addEventListener('click', function(e) {
          if (e.target === overlay) {
            WCModal.close();
          }
        });
        
        // Close on Escape key
        const escHandler = function(e) {
          if (e.key === 'Escape') {
            WCModal.close();
            document.removeEventListener('keydown', escHandler);
          }
        };
        document.addEventListener('keydown', escHandler);
        
        // Add animations
        if (!document.getElementById('modal-animations')) {
          const style = document.createElement('style');
          style.id = 'modal-animations';
          style.textContent = `
            @keyframes fadeIn {
              from { opacity: 0; }
              to { opacity: 1; }
            }
            @keyframes slideUp {
              from {
                opacity: 0;
                transform: translateY(20px);
              }
              to {
                opacity: 1;
                transform: translateY(0);
              }
            }
          `;
          document.head.appendChild(style);
        }
      },
      
      close: function() {
        const container = document.getElementById('modal-container');
        if (container) {
          container.innerHTML = '';
        }
      }
    };
    </script>

    <?php
    // Include floating chat widget if user is logged in
    if (!empty($_SESSION['user_id']) || !empty($_SESSION['employee_id'])) {
      include __DIR__ . '/floating_chat.php';
    }
    ?>

  </body>
</html>

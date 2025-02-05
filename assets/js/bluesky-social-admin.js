(function($){
    if ( document.querySelector('.bluesky-social-integration-admin') ) {

        /**
         * Navigation menu
         */
        const navItems = document.querySelectorAll('#bluesky-main-nav-tabs a');
        const tabContents = document.querySelectorAll('.bluesky-social-integration-admin-content');
        const hideTabs = function(){
            tabContents.forEach(tab => {
                tab.setAttribute('hidden', 'true');
                tab.setAttribute('aria-hidden', 'true');
            });
            navItems.forEach(item => {
                item.classList.remove('active');
                item.setAttribute('aria-current', 'false');
            });
        };

        const showCurrent = function(currentNavItem){
            currentNavItem.classList.add('active');
            currentNavItem.setAttribute('aria-current', 'true');
            
            let target = document.querySelector(`#${currentNavItem.getAttribute('aria-controls')}`);
            target.removeAttribute('hidden');
            target.setAttribute('aria-hidden', 'false');

            localStorage.setItem('bluesky-social-integration-admin-tab', currentNavItem.getAttribute('aria-controls'));
        };
        
        navItems.forEach(item => {
            item.addEventListener('click', function(e){
                e.preventDefault();
                hideTabs();
                showCurrent(this);
            });
        });

        if ( localStorage.getItem('bluesky-social-integration-admin-tab') ) {
            console.log(localStorage.getItem('bluesky-social-integration-admin-tab'));
            document.querySelector('[aria-controls="' + localStorage.getItem('bluesky-social-integration-admin-tab') + '"]').click();
        } else {
            navItems[0].click();
        }

        /**
         * Customisation Editor
         */
        const units = document.querySelectorAll('.bluesky-custom-unit');
        const styles = document.querySelector('.bluesky-social-integration-interactive-editor');

        units.forEach(unit => {
            unit.addEventListener('change', e => {
                let styleID = 'bluesky' + e.target.dataset.var;

                if ( ! document.getElementById( styleID ) ) {
                    let style = document.createElement('style');
                    style.id = styleID;
                    styles.prepend( style );
                }

                document.getElementById( styleID ).innerHTML = '.bluesky-social-integration-profile-card{' + e.target.dataset.var + ': ' + e.target.value + 'px}';

                if ( e.target.value < 10 || typeof 'e.target.value' === 'null' ) {
                    document.getElementById( styleID ).remove();
                }
            });
        });
    }
})(jQuery);
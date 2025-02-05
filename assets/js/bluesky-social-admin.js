(function($){
    if ( document.querySelector('.bluesky-social-integration-admin') ) {
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
    }
})(jQuery);
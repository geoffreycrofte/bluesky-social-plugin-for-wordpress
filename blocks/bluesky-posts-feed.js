(function(blocks, element, components) {
    const { registerBlockType } = blocks;
    const { createElement } = element;

    registerBlockType('bluesky-social/posts', {
        title: 'BlueSky Posts',
        icon: 'megaphone',
        category: 'widgets',
        
        edit: function(props) {
            return createElement(
                'div', 
                { className: 'bluesky-posts-block' },
                'BlueSky Posts (will load dynamically)'
            );
        },

        save: function() {
            return null; // Server-side rendering will handle this
        }
    });
})(
    window.wp.blocks, 
    window.wp.element, 
    window.wp.components
);
(function(blocks, serverSideRender, element) {
    const { registerBlockType } = blocks;
    const { createElement } = element;

    registerBlockType('bluesky-social/profile', {
        title: 'BlueSky Profile',
        icon: 'admin-users',
        category: 'widgets',
        
        edit: function(props) {
            return createElement(
                wp.serverSideRender, 
                {
                    block: 'bluesky-social/profile',
                    attributes: props.attributes
                }
            );
        },

        save: function() {
            return null;
        }
    });
})(
    window.wp.blocks, 
    window.wp.serverSideRender, 
    window.wp.element
);
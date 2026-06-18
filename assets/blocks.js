/* Comms.Church PCO — Gutenberg block definitions
   Uses wp.blocks, wp.element, wp.components, wp.blockEditor (all globally available).
   No build step required. */

(function (blocks, element, components, blockEditor, i18n, serverSideRender) {
  var el          = element.createElement;
  var __          = i18n.__;
  var Fragment    = element.Fragment;
  var ServerSideRender = serverSideRender;

  var InspectorControls = blockEditor.InspectorControls;
  var PanelBody         = components.PanelBody;
  var TextControl       = components.TextControl;
  var SelectControl     = components.SelectControl;
  var ToggleControl     = components.ToggleControl;
  var RangeControl      = components.RangeControl;
  var ColorPicker       = components.ColorPicker;
  var Placeholder       = components.Placeholder;
  var Spinner           = components.Spinner;
  var Notice            = components.Notice;

  var data        = window.CCPCOBlocks || {};
  var signups     = data.signups     || [];
  var configured  = data.configured  || false;
  var settingsUrl = data.settingsUrl || '';
  var globalColor = data.brandColor  || '#1a4a8a';

  // ---- Signup select options -------------------------------------------
  var signupOptions = [{ label: '— select a signup —', value: 0 }].concat(
    signups.map(function (s) { return { label: s.name + ' (ID: ' + s.id + ')', value: s.id }; })
  );

  // ---- Not-configured placeholder --------------------------------------
  function NotConfigured() {
    return el( Notice, { status: 'warning', isDismissible: false },
      __( 'PCO Registrations: API not configured. ', 'comms-church-pco' ),
      el( 'a', { href: settingsUrl }, __( 'Configure →', 'comms-church-pco' ) )
    );
  }

  // =========================================================================
  // Block 1: PCO Signup List
  // =========================================================================
  blocks.registerBlockType( 'comms-church-pco/signup-list', {
    title:       __( 'PCO Signup List', 'comms-church-pco' ),
    description: __( 'Display a grid or list of Planning Center signups.', 'comms-church-pco' ),
    category:    'comms-church-pco',
    icon:        'calendar-alt',

    edit: function (props) {
      var attrs   = props.attributes;
      var setAttr = props.setAttributes;

      if (!configured) return el( NotConfigured );

      return el( Fragment, null,

        el( InspectorControls, null,

          el( PanelBody, { title: __( 'Data & Filter', 'comms-church-pco' ), initialOpen: true },
            el( RangeControl, { label: __( 'Max Signups', 'comms-church-pco' ), value: attrs.limit, onChange: function(v){ setAttr({limit:v}); }, min:1, max:50 } ),
            el( SelectControl, { label: __( 'Status', 'comms-church-pco' ), value: attrs.filter, onChange: function(v){ setAttr({filter:v}); }, options: [ {label:'Active',value:'unarchived'}, {label:'Archived',value:'archived'} ] } ),
            el( TextControl, { label: __( 'Category Filter', 'comms-church-pco' ), value: attrs.category, onChange: function(v){ setAttr({category:v}); }, placeholder: __( "e.g. Women's Ministry", 'comms-church-pco' ) } ),
            el( ToggleControl, { label: __( 'Show Closed', 'comms-church-pco' ), checked: attrs.showClosed, onChange: function(v){ setAttr({showClosed:v}); } } )
          ),

          el( PanelBody, { title: __( 'Layout', 'comms-church-pco' ), initialOpen: false },
            el( SelectControl, { label: __( 'Display', 'comms-church-pco' ), value: attrs.display, onChange: function(v){ setAttr({display:v}); }, options: [ {label:'Tiles (grid)',value:'tiles'}, {label:'List',value:'list'} ] } ),
            attrs.display === 'tiles' && el( RangeControl, { label: __( 'Columns', 'comms-church-pco' ), value: attrs.columns, onChange: function(v){ setAttr({columns:v}); }, min:1, max:4 } ),
            el( SelectControl, { label: __( 'Image Shape', 'comms-church-pco' ), value: attrs.imageShape, onChange: function(v){ setAttr({imageShape:v}); }, options: [ {label:'Cinematic (16:9)',value:'cinematic'}, {label:'Square',value:'square'}, {label:'Portrait',value:'portrait'} ] } ),
            el( RangeControl, { label: __( 'Corner Radius (px)', 'comms-church-pco' ), value: attrs.cornerRadius, onChange: function(v){ setAttr({cornerRadius:v}); }, min:0, max:40 } )
          ),

          el( PanelBody, { title: __( 'Card Content', 'comms-church-pco' ), initialOpen: false },
            el( ToggleControl, { label: __( 'Show Date',        'comms-church-pco' ), checked: attrs.showDate,     onChange: function(v){ setAttr({showDate:v}); } } ),
            el( ToggleControl, { label: __( 'Show Location',    'comms-church-pco' ), checked: attrs.showLocation, onChange: function(v){ setAttr({showLocation:v}); } } ),
            el( ToggleControl, { label: __( 'Show Price',       'comms-church-pco' ), checked: attrs.showPrice,    onChange: function(v){ setAttr({showPrice:v}); } } ),
            el( ToggleControl, { label: __( 'Add to Calendar',  'comms-church-pco' ), checked: attrs.showCalendar, onChange: function(v){ setAttr({showCalendar:v}); } } ),
            el( ToggleControl, { label: __( 'Show Description', 'comms-church-pco' ), checked: attrs.showDesc,     onChange: function(v){ setAttr({showDesc:v}); } } ),
            el( TextControl,   { label: __( 'Button Label',     'comms-church-pco' ), value: attrs.buttonLabel, onChange: function(v){ setAttr({buttonLabel:v}); }, placeholder: __( 'Register', 'comms-church-pco' ) } ),
            el( RangeControl,  { label: __( 'Capacity (for progress bar)', 'comms-church-pco' ), value: attrs.capacity, onChange: function(v){ setAttr({capacity:v}); }, min:0, max:1000, help: __( 'Set above 0 to show a progress bar. You set the limit — not pulled from PCO.', 'comms-church-pco' ) } )
          ),

          el( PanelBody, { title: __( 'Brand Color', 'comms-church-pco' ), initialOpen: false },
            el( 'p', { style:{fontSize:'.8rem',color:'#64748b',marginBottom:'.5rem'} }, __( 'Leave unchanged to use global brand color from Settings.', 'comms-church-pco' ) ),
            el( ColorPicker, { color: attrs.brandColor || globalColor, onChangeComplete: function(c){ setAttr({brandColor: c.hex}); } } )
          )

        ),

        // Editor preview via server-side render
        el( ServerSideRender, {
          block: 'comms-church-pco/signup-list',
          attributes: attrs,
        } )

      );
    },

    save: function () { return null; } // Server-side rendered
  });

  // =========================================================================
  // Block 2: PCO Signup Card
  // =========================================================================
  blocks.registerBlockType( 'comms-church-pco/signup-card', {
    title:       __( 'PCO Signup Card', 'comms-church-pco' ),
    description: __( 'Full detail card for a single Planning Center signup.', 'comms-church-pco' ),
    category:    'comms-church-pco',
    icon:        'tickets-alt',

    edit: function (props) {
      var attrs   = props.attributes;
      var setAttr = props.setAttributes;

      if (!configured) return el( NotConfigured );

      return el( Fragment, null,

        el( InspectorControls, null,

          el( PanelBody, { title: __( 'Signup', 'comms-church-pco' ), initialOpen: true },
            el( SelectControl, { label: __( 'Select Signup', 'comms-church-pco' ), value: attrs.signupId, onChange: function(v){ setAttr({signupId:parseInt(v,10)||0}); }, options: signupOptions } ),
            el( TextControl,   { label: __( 'Or enter ID manually', 'comms-church-pco' ), value: attrs.signupId ? String(attrs.signupId) : '', onChange: function(v){ setAttr({signupId:parseInt(v,10)||0}); }, type:'number' } )
          ),

          el( PanelBody, { title: __( 'Content', 'comms-church-pco' ), initialOpen: false },
            el( ToggleControl, { label: __( 'Show Description', 'comms-church-pco' ), checked: attrs.showDesc,     onChange: function(v){ setAttr({showDesc:v}); } } ),
            el( ToggleControl, { label: __( 'Show Times',       'comms-church-pco' ), checked: attrs.showTimes,    onChange: function(v){ setAttr({showTimes:v}); } } ),
            el( ToggleControl, { label: __( 'Show Location',    'comms-church-pco' ), checked: attrs.showLocation, onChange: function(v){ setAttr({showLocation:v}); } } ),
            el( ToggleControl, { label: __( 'Show Tickets',     'comms-church-pco' ), checked: attrs.showTickets,  onChange: function(v){ setAttr({showTickets:v}); } } ),
            el( ToggleControl, { label: __( 'Add to Calendar',  'comms-church-pco' ), checked: attrs.showCalendar, onChange: function(v){ setAttr({showCalendar:v}); } } ),
            el( TextControl,   { label: __( 'Button Label',     'comms-church-pco' ), value: attrs.buttonLabel, onChange: function(v){ setAttr({buttonLabel:v}); }, placeholder: __( 'Register', 'comms-church-pco' ) } )
          ),

          el( PanelBody, { title: __( 'Brand Color', 'comms-church-pco' ), initialOpen: false },
            el( ColorPicker, { color: attrs.brandColor || globalColor, onChangeComplete: function(c){ setAttr({brandColor:c.hex}); } } )
          )

        ),

        attrs.signupId
          ? el( ServerSideRender, { block: 'comms-church-pco/signup-card', attributes: attrs } )
          : el( Placeholder, { icon: 'tickets-alt', label: __( 'PCO Signup Card', 'comms-church-pco' ), instructions: __( 'Select a signup in the sidebar to display its details.', 'comms-church-pco' ) } )

      );
    },

    save: function () { return null; }
  });

  // =========================================================================
  // Block 3: PCO Register Button
  // =========================================================================
  blocks.registerBlockType( 'comms-church-pco/register-button', {
    title:       __( 'PCO Register Button', 'comms-church-pco' ),
    description: __( 'A register button linked to a Planning Center signup.', 'comms-church-pco' ),
    category:    'comms-church-pco',
    icon:        'button',

    edit: function (props) {
      var attrs   = props.attributes;
      var setAttr = props.setAttributes;

      if (!configured) return el( NotConfigured );

      return el( Fragment, null,

        el( InspectorControls, null,

          el( PanelBody, { title: __( 'Signup', 'comms-church-pco' ), initialOpen: true },
            el( SelectControl, { label: __( 'Select Signup', 'comms-church-pco' ), value: attrs.signupId, onChange: function(v){ setAttr({signupId:parseInt(v,10)||0}); }, options: signupOptions } ),
            el( TextControl, { label: __( 'Or enter ID manually', 'comms-church-pco' ), value: attrs.signupId ? String(attrs.signupId) : '', onChange: function(v){ setAttr({signupId:parseInt(v,10)||0}); }, type:'number' } )
          ),

          el( PanelBody, { title: __( 'Button', 'comms-church-pco' ), initialOpen: false },
            el( TextControl, { label: __( 'Label', 'comms-church-pco' ), value: attrs.label, onChange: function(v){ setAttr({label:v}); }, placeholder: __( 'Register', 'comms-church-pco' ) } ),
            el( TextControl, { label: __( 'Extra CSS Class', 'comms-church-pco' ), value: attrs.extraClass, onChange: function(v){ setAttr({extraClass:v}); } } )
          ),

          el( PanelBody, { title: __( 'Brand Color', 'comms-church-pco' ), initialOpen: false },
            el( ColorPicker, { color: attrs.brandColor || globalColor, onChangeComplete: function(c){ setAttr({brandColor:c.hex}); } } )
          )

        ),

        el( ServerSideRender, { block: 'comms-church-pco/register-button', attributes: attrs } )

      );
    },

    save: function () { return null; }
  });

}(
  window.wp.blocks,
  window.wp.element,
  window.wp.components,
  window.wp.blockEditor,
  window.wp.i18n,
  window.wp.serverSideRender
));

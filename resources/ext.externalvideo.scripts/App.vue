<template>
  <cdx-dialog
      v-model:open="open"
      title="Add an external video"
      :use-close-button="true"
      :primary-action="primaryAction"
      :default-action="defaultAction"
      @primary="onPrimaryAction"
      @default="open = false"
  >
    <cdx-field>
      <cdx-text-input v-model="inputValue"></cdx-text-input>
      <template #label>
        URL
      </template>
      <template #help-text>
        Enter the URL of the video you wish to add to the wiki. Please note it must be one of the supported providers.
      </template>
    </cdx-field>
  </cdx-dialog>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxDialog, CdxTextInput, CdxField } = require( '../codex.js' );

module.exports = defineComponent( {
  name: 'ExternalVideo',
  components: {
    CdxDialog,
    CdxTextInput,
    CdxField
  },
  setup() {
    const open = ref( false );
    const inputValue = ref( '' );
    const rest = new mw.Rest();

    const addButton = document.querySelector( '#ca-add-video' );

    addButton.addEventListener( 'click', () => {
      open.value = true;
    });

    const primaryAction = {
      label: 'Add Video',
      actionType: 'progressive'
    };

    const defaultAction = {
      label: 'Cancel'
    };

    function onPrimaryAction() {
      open.value = false;

      rest.post( `/externalvideo/v0/video`, {
        url: inputValue.value,
        token: mw.user.tokens.get( 'csrfToken' )
      } ).then( ( data ) => {
        mw.notify( mw.message( "externalvideo-added" ).text(), { type: 'success' } );
        if ( data && data.fileUrl ) {
          window.location.href = data.fileUrl;
        }
      } ).catch( () => {
        mw.notify( mw.message( "externalvideo-error" ).text(), { type: 'error' } );
      } );
    }

    return {
      open,
      primaryAction,
      defaultAction,
      onPrimaryAction,
      inputValue
    };

  }
} );
</script>

<style lang="less">
</style>

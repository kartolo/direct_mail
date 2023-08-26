require(['TYPO3/CMS/Core/Ajax/AjaxRequest'], function (AjaxRequest) {
  "use strict";

  var uid = document.getElementById('dm-page-uid');
  if(uid === null) {
    return;
  }

  // https://developer.mozilla.org/en-US/docs/Web/API/Request/mode
  const init = {
    mode: 'cors'
  };

  var config = {
    'uid' : parseInt(uid.getAttribute('value'))
  };
  var value = null;

  const saveConfigurationButton = document.getElementById('save-configuration');

  saveConfigurationButton.closest('#pageTS').querySelectorAll("[name^='pageTS']").forEach((el) => {
    config[el.getAttribute('name')] = null;

    el.addEventListener('change', (event) => {
      if(el.tagName == 'SELECT') {
        value = el.options[el.selectedIndex].value;
      }
      config[el.getAttribute('name')] = value;
    });

    el.addEventListener('input', (event) => {
      if(el.tagName == 'INPUT') {
        if(el.getAttribute('type') == 'text') {
          value = event.target.value.trim();
        }
        else if(el.getAttribute('type') == 'number') {
          value = parseInt(event.target.value.trim());
        }
        else if(el.getAttribute('type') == 'checkbox') {
          if(event.target.checked) {
            value = parseInt(event.target.value.trim());
          }
        }
      }
      config[el.getAttribute('name')] = value;
    });
  });

  saveConfigurationButton.addEventListener('click', function() {
      const randomNumber = Math.ceil(Math.random() * 32);
      new AjaxRequest(TYPO3.settings.ajaxUrls.directmail_configuration_update)
        .withQueryArguments({input: randomNumber})
        .get()
        .then(async function (response) {
        const resolved = await response.resolve();
        console.log(resolved.result);
      });
  });
});

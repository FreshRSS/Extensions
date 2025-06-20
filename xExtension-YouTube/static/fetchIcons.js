'use strict';

/* globals context */

function initFetchBtn() {
  const fetchIcons = document.querySelector('button[value="iconFetchFinish"]');
  if (!fetchIcons) {
    return;
  }

  const i18n = context.extensions.yt_i18n;

  document.querySelectorAll('#yt_action_btn').forEach(el => el.style.marginBottom = '1rem');

  fetchIcons.removeAttribute('disabled');
  fetchIcons.removeAttribute('title');

  fetchIcons.onclick = function(e) {
    e.preventDefault();

    fetchIcons.onclick = null;
    fetchIcons.disabled = true;
    fetchIcons.form.onsubmit = (e) => e.preventDefault();
    fetchIcons.parentElement.insertAdjacentHTML('afterend', `
    <hr><br>
    <center>
        ${i18n.fetching_icons}: <b id="iconFetchCount">…</b> • <b id="iconFetchChannel">…</b>
    </center><br>
    `);

    const iconFetchCount = document.querySelector('b#iconFetchCount');
    const iconFetchChannel = document.querySelector('b#iconFetchChannel');

    const configureUrl = fetchIcons.form.action;

    function ajaxBody(action, args) {
      return new URLSearchParams({
        '_csrf': context.csrf,
        'yt_action_btn': 'ajax' + action,
        ...args
      })
    }

    fetch(configureUrl, {
      method: "POST",
      body: ajaxBody('GetYtFeeds'),
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      }
    }).then(resp => {
      if (!resp.ok) {
        return;
      }
      return resp.json();
    }).then(json => {
      let completed = 0;
      json.forEach(async (feed) => {
        await fetch(configureUrl, {
          method: "POST",
          body: ajaxBody('FetchIcon', {'id': feed.id}),
          headers: {
            "Content-Type": "application/x-www-form-urlencoded"
          }
        }).then(async () => {
          iconFetchChannel.innerText = feed.title;
          iconFetchCount.innerText = `${++completed}/${json.length}`;
          if (completed === json.length) {
            fetchIcons.disabled = false;
            fetchIcons.form.onsubmit = null;
            fetchIcons.click();
          }
        });
      });
    })
  }
}

window.addEventListener('load', function() {
  if (document.querySelector('#slider')) {
    document.querySelector('#slider').addEventListener('freshrss:slider-load', initFetchBtn);
  }
  initFetchBtn();
});

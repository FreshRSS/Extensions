/* globals $ */

(function reading_time() {
    'use strict';

    var reading_time = {
        flux_list: null,
        flux: null,
        textContent: null,
        words_count: null,
        read_time: null,
        reading_time: null,

 init: function() {
     var flux_list = document.querySelectorAll('[id^="flux_"]');

     for (var i = 0; i < flux_list.length; i++) {

        if ("readingTime" in flux_list[i].dataset) {
            continue;
        }

        reading_time.flux = flux_list[i];

        reading_time.words_count = reading_time.flux_words_count(flux_list[i]); // count the words
        reading_time.reading_time = reading_time.calc_read_time(reading_time.words_count, 300); // change this number (in words) to your prefered reading speed

        flux_list[i].dataset.readingTime = reading_time.reading_time;

         if (document.body.clientWidth <= 840) { // in mobile mode, the feed name is not visible (there is only the favicon)
             // add the reading time right before article's title
             // in that case, [Time] - [Title] format is used instead of a "|" (as it looks better and doesn't take much more space)
             if ( document.querySelector("#" + reading_time.flux.id + " ul.horizontal-list li.item.title a").textContent.substring(0,(reading_time.reading_time + 'm - ').length) != reading_time.reading_time + 'm - ' ) {
                 document.querySelector("#" + reading_time.flux.id + " ul.horizontal-list li.item.title a").textContent = reading_time.reading_time + 'm - ' + document.querySelector("#" + reading_time.flux.id + " ul.horizontal-list li.item.title a").textContent;
             }
         } else {
             // add the reading time just after the feed name
             if ( document.querySelector("#" + reading_time.flux.id + " ul.horizontal-list li.item.website").textContent.substring(1, (reading_time.reading_time + 'm|').length + 1) != reading_time.reading_time + 'm|' ) {
                 document.querySelector("#" + reading_time.flux.id + " ul.horizontal-list li.item.website").childNodes[0].childNodes[2].textContent = reading_time.reading_time + 'm| ' + document.querySelector("#" + reading_time.flux.id + " ul.horizontal-list li.item.website").childNodes[0].childNodes[2].textContent;
             }
         }

     }
 },

 flux_words_count: function flux_words_count(flux) {

    reading_time.textContent = flux.querySelector('.flux_content .content').textContent; // get textContent, from the article itself (not the header, not the bottom line).

    // split the text to count the words correctly (source: http://www.mediacollege.com/internet/javascript/text/count-words.html)
    reading_time.textContent = reading_time.textContent.replace(/(^\s*)|(\s*$)/gi,"");//exclude  start and end white-space
    reading_time.textContent = reading_time.textContent.replace(/[ ]{2,}/gi," ");//2 or more space to 1
    reading_time.textContent = reading_time.textContent.replace(/\n /,"\n"); // exclude newline with a start spacing

    return reading_time.textContent.split(' ').length;
 },

 calc_read_time : function calc_read_time(wd_count, speed) {
    reading_time.read_time = Math.round(wd_count/speed);
    if (reading_time.read_time === 0) { reading_time.read_time = '<1'; }
    return reading_time.read_time;
 },
    };

    function add_load_more_listener() {
        reading_time.init();
        document.body.addEventListener('freshrss:load-more', function (e) {
            reading_time.init();
        });
    }

    if (document.readyState && document.readyState !== 'loading') {
        add_load_more_listener();
    } else if (document.addEventListener) {
        document.addEventListener('DOMContentLoaded', add_load_more_listener, false);
    }

}());

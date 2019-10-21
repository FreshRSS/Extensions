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
     // console.log(flux_list)

     for (var i = 0; i < flux_list.length; i++) {
         //console.log($("div[id^='flux']")[i], "Length (words): ", reading_time.flux_words_count($("div[id^='flux']")[i]))

         reading_time.flux = flux_list[i];

         reading_time.words_count = reading_time.flux_words_count(flux_list[i]); // count the words
         reading_time.reading_time = reading_time.calc_read_time(reading_time.words_count, 300); // change this number (in words) to your prefered reading speed


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

     reading_time.textContent = flux.querySelector('.flux_content').textContent; // get textContent, from the article itself (not the header, not the bottom line). `childNodes[2].childNodes[1]` gives : `<div class="content no_limit">` element

     // split the text to count the words correctly (source: http://www.mediacollege.com/internet/javascript/text/count-words.html)
     reading_time.textContent = reading_time.textContent.replace(/(^\s*)|(\s*$)/gi,"");//exclude  start and end white-space
     reading_time.textContent = reading_time.textContent.replace(/[ ]{2,}/gi," ");//2 or more space to 1
     reading_time.textContent = reading_time.textContent.replace(/\n /,"\n"); // exclude newline with a start spacing

     /// This part ↓↓↓ is not working if the content of the title bar is changed by the user… need to do that in proper, more robust way
     // flux.childNodes[0].childNodes[2].textContent.split(' ').length - // excluding website length
     // flux.childNodes[0].childNodes[4].textContent.split(' ').length - // excluding date length
     // flux.childNodes[0].childNodes[6].textContent.split(' ').length;  // excluding title length
     // Not 100% acurate as it doesn't check if there are shown at the bottom of the article
     return reading_time.textContent.split(' ').length - //raw number of words
     15 ; // Temporary fix for excluding website/date/title text. It assumes than the title (+ author) label is rougly 15 words long.
 },

 calc_read_time : function calc_read_time(wd_count, speed) {
     reading_time.read_time = Math.round(wd_count/speed);
     if (reading_time.read_time === 0) { reading_time.read_time = '<1'; }
     //console.log('Reading time: ', reading_time.read_time)
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

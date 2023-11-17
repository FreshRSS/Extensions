$(document).ready(function(){
    function monitorEntry(monitorCallback) {
        const targetNode = document.getElementById('stream');
        const config = { attributes: false, childList: true, subtree: false};
        const callback = function(mutationsList, observer) {
            for(let mutation of mutationsList) {
                if (mutation.type === 'childList') {
                    monitorCallback(mutationsList);
                }
                    }
        };
        const observer = new MutationObserver(callback);
        observer.observe(targetNode, config);
        //observer.disconnect();
    };
    monitorEntry(colorize);
});


function colorize(entries){
    let entry = document.querySelectorAll('.flux_header');
    entry.forEach((e,i)=>{
        let cl = stringToColour(e.querySelector('.website').textContent)+'12';
        e.style.background=cl;
    });
};


const stringToColour = (str) => {
  let hash = 0;
  str.split('').forEach(char => {
    hash = char.charCodeAt(0) + ((hash << 5) - hash)
  })
  let colour = '#'
  for (let i = 0; i < 3; i++) {
    const value = (hash >> (i * 8)) & 0xff
    colour += value.toString(16).padStart(2, '0')
  }
  return colour
};

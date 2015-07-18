var frisby = require('frisby');
var fs = require('fs');

var testUrl = 'http://localhost/fossasia/common.api.fossasia.net/ics-collector/CalendarAPI.php';
var report = '';

frisby.create('Test availability')
  .get(testUrl)
  .expectStatus(200)
.toss();

frisby.create('Test source parameter required')
  .get(testUrl)
  .expectStatus(200)
  .expectHeaderContains('content-type', 'application/json')
  .expectBodyContains("Missing required parameter : source")
.toss();

frisby.create('Test ics mode')
  .get(testUrl + '?source=all&format=ics')
  .expectStatus(200)
  .expectHeaderContains('content-type', 'text/ics')
  .expectBodyContains('BEGIN:VCALENDAR')
  .expectBodyContains('BEGIN:VEVENT')
  .expectBodyContains('END:VCALENDAR')
.toss();

frisby.create('Get number of events')
  .get(testUrl + '?source=all')
  .expectStatus(200)
    .afterJSON (function(data) {
    report += 'Number of events : ' + data.length + '\n';
  })
.toss();

frisby.create('Test fields parameter')
  .get(testUrl + '?source=all&fields=url')
  .expectStatus(200)
  .afterJSON(function(data) {
    for (var key in data) {
      for (var eventKey in data[key]) {
        if (eventKey != 'url' || eventKey != 'location') {
          expect(eventKey).toMatch('url');
        }
      }
    }
    return false;
  })
.toss();


var fields = ['start', 'end', 'location', 'source', 'url', 'geolocation', 'stamp', 'created', 'last_modified', 'summary', 'description'];
var url = testUrl + '?source=all&fields=';
var prefix = '';
for (var key in fields) {
  url += prefix + fields[key];
  prefix = ',';
}
frisby.create('Get all fields for analysis')
  .get(url)
  .expectStatus(200)
  .afterJSON(function (data) {
    report += 'Events with field : \n';
    for (var key in fields) {
      report += ' - ' + fields[key] + ' : ' + countFields(data, fields[key]) + '\n';
    }
    var now = currentTimeToString();
    fs.writeFile('reports/' + now + '.txt', report, function(err) {
      if (err) throw err;
      console.log(report);
      console.log('API analysis report is saved in ./reports/' + now + '.txt' + '\n\n');
    });
  })
.toss();


function countFields(data, field) {
  var cnt = 0;
  for (var key in data) {
    if (data[key][field]) {
      cnt++;
    }
  }
  return cnt;
}

function currentTimeToString() {
 var date = new Date();

 function toTwoDigits(string) {
  string = string.toString();
  if (string.length == 1) 
    string = '0' + string;
  return string;
 }
 return date.getFullYear() 
  + '-' + toTwoDigits(date.getMonth()) 
  + '-' + toTwoDigits(date.getDate()) 
  + 'T' + toTwoDigits(date.getHours()) 
  + ':' + toTwoDigits(date.getMinutes())
  + ':' + toTwoDigits(date.getSeconds());
}
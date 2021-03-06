How to publish a new episode
----------------------------


1. Open "verses.csv" in the "data" directory with OpenOffice
------------------------------------------------------------

Set the options:

- charset = UTF-8
- cell separator = tab (tabulation)
- text delimiter = " (double-quote)


2. Review and fix the episode just being translated
---------------------------------------------------

- read aloud, the translation must "flow" naturally although remaining close to the original text
- avoid repetitions
- use "vous" instead of "tu" in most cases (vouvoiement)
- use the present tense in most cases
- correct typos
- verify that the "original-verse-to-confirm" and "translated-verse-to-confirm" contents are in sync with the translation


3. Create the blog message
--------------------------

- enter a title for the episode
- upload the image, choose the medium size
- display the HTML, copy the src URL to be pasted later,
  note that it is important to pick the right size as the "s320" URL segment will be used to reduce the image size automatically
- publish the blog message

Note that at this point, this message becomes the entry point of the blog, so the (current) publishing should ideally be done quickly.


4. Complete the episode information
-----------------------------------

The episode information must be updated in the first line of the episode:

- add the story title in the "story-title" cell if this is the first episode of a story (leave blank otherwise)
- add the episode title in the "episode-title" cell, this should ideally be the same title that was used to create the blog message
- add the url of the new blog message in the "url" cell
- add the url to the episode image in the "image-src" cell, this is src URL captured previously.
- check the section original title in the "section-original-title" cell if this is the first episode of a section in the book (leave blank otherwise)
- check the section translated title in the "section-translated-title" cell if this is the first episode of a section in the book (leave blank otherwise)
- save and close the file, do not change the CSV format settings and extension


5. Update the next episode to translate (recommended)
-----------------------------------------------------

- add the number of the next episode in the "episode-number" cell in the first line of this episode
- confirm the letter "x" in the "is-last-verse" cell in the last line of this episode

Note that this step may be done later.


6. Publish the episode
----------------------

- run the command line: publish -u <login> -p <password> -a
- copy & paste the content of "widgets/introduction.html" to the corresponding blog widget  if it was updated
- copy & paste the content of "widgets/table-of-contents.html" to the corresponding blog widget  if it was updated
- copy & paste the content of "widgets/copyright.html" to the corresponding blog widget,
  this should only be necessary once every year when publishing the first episode of a year

Note: update the project follow-up in google docs.
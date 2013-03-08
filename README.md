twig-po
=======

Extract translation keys from twig templates and move them to PO

Instructions
------------

Fork and install (you need composer for that!):

    git clone git@github.com:your-user/twig-po.git
    cd twig-po
    composer install

Then, go to folder and execute:

    ./console find /path/to/twig/templates  /path/to/messages.po -d -v -o

Once you see that nothing wrong is going to happen then

    ./console find /path/to/twig/templates  /path/to/messages.po

For help:

    ./console help find

Once you have the PO translated, convert to .mo with your editor or command line:

    msgfmt -cv -o messages.mo messages.po

(Note: you need to have gettext installed for this command)

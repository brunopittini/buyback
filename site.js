
//////// BEGIN KNOCKOUT CODE ////////

var headers = ['ISBN', 'Title', 'Neebo (Nebraska Bookstore)', 'Follett (University Bookstore)', 'Amazon'];

var Utils = {
    cleanIsbn: function (isbn) {
        return $.trim(isbn.replace(/-/g, ''));
    }
}

var BuybackViewModel = function () {
    var self = this;
    
    self.inputIsbn = ko.observable('');
    
    self.searchIsbn = ko.computed(function () {
        return Utils.cleanIsbn(self.inputIsbn());
    });
    
    self.books = ko.observableArray([]);
    
    self.addBook = function (isbn) {
        self.books.push(new Book(isbn));
    }
    
    self.search = function () {
        self.addBook(self.searchIsbn());
        self.inputIsbn('');
    }
}

var Book = function (isbn) {
    var self = this;
    
    self.isbn = ko.observable(isbn);
    self.title = ko.observable('');
    self.author = ko.observable('');
    self.edition = ko.observable('');
    
    self.providers = ko.observableArray([]);
    
    self.fetchData = function () {
        $.get('get.php', { 'isbn': self.isbn() }, function (data) {
            self.providers(ko.utils.arrayMap($.parseJSON(data), function (item) {
                return new Provider(item);
            }));
            self.title(self.providers()[0].name);
            if (self.title() == "Sellback not available on this item" && self.providers()[2].name != "") {
                self.title(self.providers()[2].name);
            }
        });
    }
    
    self.fetchData();
}

var Provider = function (data) {
    var self = this;
    
    self.provider = ko.observable(data.Provider);
    self.name = data.Name;
    self.author = data.Author;
    self.edition = data.Edition;
    self.isbn = data.Isbn;
    self.price = ko.observable(data.Price);
}

var buybackVM = new BuybackViewModel();

$(document).ready(function () {
    ko.applyBindings(buybackVM, $('#content')[0]);
})
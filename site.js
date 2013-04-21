
//////// BEGIN KNOCKOUT CODE ////////

var headers = ['ISBN', 'Title', 'Neebo (Nebraska Bookstore)', 'Follett (University Bookstore)', 'Amazon'];

var Utils = {
    cleanIsbn: function (isbn) {
        return isbn.replace(/[^0-9]/g, '');
    },
    isNullOrEmpty: function (s) {
        return (s == null || s == undefined || s == '');
    },
    test: function () {
        buybackVM.addBook('9780262033848');
        buybackVM.addBook('9780805091182');
        buybackVM.addBook('9781118226155');
        buybackVM.addBook('9780738205373');
        buybackVM.addBook('9780071749275');
        buybackVM.addBook('9781111773397');
        buybackVM.addBook('9780446563048');
        buybackVM.addBook('0020303955');
        buybackVM.addBook('9781845116545');
        buybackVM.addBook('');
        buybackVM.addBook('');
        buybackVM.addBook('');
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
        if (!Utils.isNullOrEmpty(isbn)) {
            self.books.push(new Book(isbn));
        }
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
            data = $.parseJSON(data);
            self.title(data.Title);
            self.providers(ko.utils.arrayMap(data.Providers, function (item) {
                return new Provider(item);
            }));
        });
    }
    
    self.fetchData();
}

var Provider = function (data) {
    var self = this;
    
    self.provider = ko.observable(data.Provider);
    self.title = data.Title;
    self.author = data.Author;
    self.edition = data.Edition;
    self.isbn = data.Isbn;
    self.price = ko.observable(data.Price);
    self.displayPrice = ko.computed(function () {
        var price = self.price();
        if (price.length > 1 && price.indexOf('.') == price.length-2) {
            price = price + "0";
        }
        return '$' + (price.substr(-3) == ".00" ? price.substr(0,price.length-3) : price);
    });
}

var buybackVM = new BuybackViewModel();

$(document).ready(function () {
    ko.applyBindings(buybackVM, $('body')[0]);
})
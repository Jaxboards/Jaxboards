module.exports = function pagesHelper({ hash }) {
  const currentPage = hash.page || 0;
  const pageNumbers = [];

  for (let i = 0; i < hash.total; i += 1) {
    pageNumbers.push({
      class: i === currentPage ? 'active' : '',
      number: i,
      displayedNumber: i + 1
    });
  }

  return pageNumbers;
};

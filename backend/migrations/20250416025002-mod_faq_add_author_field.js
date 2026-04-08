module.exports = {
  async up(db) {
    const faqs = db.collection('faqs');

    const result = await faqs.updateMany(
      { author: { $exists: false } },
      { $set: { author: null } }
    );

    console.log(`Migrate up: Updated ${result.modifiedCount} faqs`);
  },

  async down(db) {
    const faqs = db.collection('faqs');
    const result = await faqs.updateMany(
      {},
      {
        $unset: {
          author: ""
        }
      }
    );
    console.log(`Migrate down: Updated ${(await result).modifiedCount} faqs`);
  }
};

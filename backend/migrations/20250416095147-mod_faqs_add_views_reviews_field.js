/**
 * MongoDB migration to populate missing `reviews` and `views` fields in posts.
 */
module.exports = {
  async up(db) {
    const faqs = db.collection('faqs');

    const cursor = faqs.find({
      $or: [
        { reviews: { $exists: false } },
        { views: { $exists: false } }
      ]
    });

    let updatedCount = 0;

    while (await cursor.hasNext()) {
      const faq = await cursor.next();
      if (!faq) continue;

      const updateFields = {};

      if (faq.reviews === undefined) {
        updateFields.reviews = randomReviewScore();
      }

      if (faq.views === undefined) {
        updateFields.views = randomViews();
      }

      if (Object.keys(updateFields).length > 0) {
        await faqs.updateOne(
          { _id: faq._id },
          { $set: updateFields }
        );
        updatedCount++;
      }
    }

    console.log(`✅ UP migration complete. Updated ${updatedCount} faqs.`);
  },

  async down(db) {
    const faqs = db.collection('faqs');

    const result = await faqs.updateMany(
      {},
      {
        $unset: {
          reviews: 0.0,
          views: 0,
        },
      }
    );

    console.log(`↩️ DOWN migration complete. Unset fields in ${result.modifiedCount} faqs.`);
  }
};

/**
 * Generate random views count between 100 and 10,000,000.
 */
function randomViews(min = 100, max = 10_000_000) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
 * Generate random review count between 0 and 5.
 */
function randomReviewScore(min = 4.5, max = 5.0, decimal = 1) {
  const factor = Math.pow(10, decimal);
  const raw = Math.random() * (max - min) + min;
  return Math.round(raw * factor) / factor;
}

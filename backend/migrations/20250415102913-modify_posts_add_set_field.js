module.exports = {
  async up(db) {
    const posts = db.collection('posts');

    const cursor = posts.find({
      $or: [
        { reviews: { $exists: false } },
        { pinTop: { $exists: false } }
      ]
    });

    let updatedCount = 0;

    while (await cursor.hasNext()) {
      const post = await cursor.next();
      if (!post) continue;

      const updateFields = {};

      if (post.reviews === undefined) updateFields.reviews = 0;
      if (post.pinTop === undefined) updateFields.pinTop = false;

      if (Object.keys(updateFields).length > 0) {
        await posts.updateOne(
          { _id: post._id },
          { $set: updateFields }
        );
        updatedCount++;
      }
    }

    console.log(`✅ UP migration complete. Updated ${updatedCount} posts.`);
  },

  async down(db) {
    const posts = db.collection('posts');

    const result = await posts.updateMany(
      {},
      {
        $unset: {
          reviews: "",
          pinTop: ""
        },
      }
    );

    console.log(`↩️ DOWN migration complete. Unset fields in ${result.modifiedCount} posts.`);
  }
};
